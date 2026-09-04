import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ElementApi, ElementApiError, idempotencyKey } from './elementApi';
import { saveState as makeSaveState, stateForError } from './saveState';
import type { EditorBootstrap, EditorElement, LockLease, SaveState } from './types';

const DEBOUNCE_MS = 1_200;

interface AutosaveCallbacks {
  readonly getElement: (id: number) => EditorElement | null;
  readonly replaceElement: (oldId: number, next: EditorElement) => void;
  readonly removeElement: (id: number) => void;
  readonly onIdChange: (oldId: number, nextId: number) => void;
}

interface AutosaveController {
  readonly state: SaveState;
  readonly markDirty: (elementId: number, delay?: number) => void;
  readonly cancelLocal: (elementId: number) => void;
  readonly deleteElement: (element: EditorElement) => Promise<boolean>;
  readonly retry: () => void;
}

function isNetworkError(error: unknown): boolean {
  return error instanceof ElementApiError && error.status === 0;
}

function leaseIsFresh(lease: LockLease | undefined): lease is LockLease {
  return lease !== undefined && Date.parse(lease.expires_at) > Date.now() + 2_000;
}

export function useElementAutosave(
  apiConfig: EditorBootstrap['api'],
  callbacks: AutosaveCallbacks,
): AutosaveController {
  const api = useMemo(() => new ElementApi(apiConfig), [apiConfig]);
  const callbackRef = useRef(callbacks);
  callbackRef.current = callbacks;
  const dirtyRef = useRef(new Set<number>());
  const savingRef = useRef(new Set<number>());
  const revisionsRef = useRef(new Map<number, number>());
  const timersRef = useRef(new Map<number, number>());
  const keysRef = useRef(new Map<number, string>());
  const leasesRef = useRef(new Map<number, LockLease>());
  const pendingDeletesRef = useRef(new Map<number, EditorElement>());
  const failureRef = useRef<SaveState | null>(null);
  const [state, setState] = useState<SaveState>(() => makeSaveState('saved'));

  const refreshState = useCallback((): void => {
    if (!navigator.onLine && dirtyRef.current.size > 0) {
      setState(makeSaveState('offline'));
      return;
    }
    if (savingRef.current.size > 0) {
      setState(makeSaveState('saving'));
      return;
    }
    if (failureRef.current !== null) {
      setState(failureRef.current);
      return;
    }
    setState(makeSaveState(dirtyRef.current.size > 0 ? 'dirty' : 'saved'));
  }, []);

  const clearTimer = useCallback((elementId: number): void => {
    const timer = timersRef.current.get(elementId);
    if (timer !== undefined) window.clearTimeout(timer);
    timersRef.current.delete(elementId);
  }, []);

  const lockFor = useCallback(async (elementId: number, force = false): Promise<LockLease> => {
    const cached = leasesRef.current.get(elementId);
    if (!force && leaseIsFresh(cached)) return cached;

    let lease: LockLease;
    try {
      lease = await api.acquireLock(elementId);
    } catch (error) {
      if (!isNetworkError(error)) throw error;
      lease = await api.acquireLock(elementId);
    }
    leasesRef.current.set(elementId, lease);

    return lease;
  }, [api]);

  const scheduleRef = useRef<(elementId: number, delay: number) => void>(() => undefined);
  const saveOne = useCallback(async (elementId: number): Promise<void> => {
    clearTimer(elementId);
    if (!navigator.onLine) {
      refreshState();
      return;
    }
    const element = callbackRef.current.getElement(elementId);
    if (element === null || !dirtyRef.current.has(elementId)) return;
    const revision = revisionsRef.current.get(elementId) ?? 0;
    savingRef.current.add(elementId);
    failureRef.current = null;
    refreshState();

    try {
      let persisted: EditorElement;
      if (element.id < 0) {
        const key = keysRef.current.get(element.id) ?? idempotencyKey(element.id);
        keysRef.current.set(element.id, key);
        try {
          persisted = await api.create(element, key);
        } catch (error) {
          if (!isNetworkError(error)) throw error;
          persisted = await api.create(element, key);
        }
      } else {
        let lease = await lockFor(element.id);
        try {
          persisted = await api.update(element, lease.lock_token);
        } catch (error) {
          if (error instanceof ElementApiError && error.status === 423) {
            leasesRef.current.delete(element.id);
            lease = await lockFor(element.id, true);
            persisted = await api.update(element, lease.lock_token);
          } else if (isNetworkError(error) && leaseIsFresh(lease)) {
            persisted = await api.update(element, lease.lock_token);
          } else {
            throw error;
          }
        }
      }

      const current = callbackRef.current.getElement(elementId);
      const currentRevision = revisionsRef.current.get(elementId) ?? revision;
      const nextId = persisted.id;
      if (current === null) {
        dirtyRef.current.delete(elementId);
      } else if (currentRevision === revision) {
        callbackRef.current.replaceElement(elementId, persisted);
        dirtyRef.current.delete(elementId);
      } else {
        const rebased = { ...current, id: nextId, version: persisted.version };
        callbackRef.current.replaceElement(elementId, rebased);
        dirtyRef.current.delete(elementId);
        dirtyRef.current.add(nextId);
        revisionsRef.current.set(nextId, currentRevision);
        scheduleRef.current(nextId, 0);
      }

      if (nextId !== elementId) {
        callbackRef.current.onIdChange(elementId, nextId);
        const nextRevision = revisionsRef.current.get(nextId) ?? currentRevision;
        revisionsRef.current.delete(elementId);
        revisionsRef.current.set(nextId, nextRevision);
        keysRef.current.delete(elementId);
      }
      failureRef.current = null;
    } catch (error) {
      failureRef.current = stateForError(error, navigator.onLine);
    } finally {
      savingRef.current.delete(elementId);
      refreshState();
    }
  }, [clearTimer, lockFor, refreshState, api]);

  const schedule = useCallback((elementId: number, delay: number): void => {
    clearTimer(elementId);
    if (!navigator.onLine) {
      refreshState();
      return;
    }
    const timer = window.setTimeout(() => void saveOne(elementId), Math.max(0, delay));
    timersRef.current.set(elementId, timer);
  }, [clearTimer, refreshState, saveOne]);
  scheduleRef.current = schedule;

  const markDirty = useCallback((elementId: number, delay = DEBOUNCE_MS): void => {
    dirtyRef.current.add(elementId);
    revisionsRef.current.set(elementId, (revisionsRef.current.get(elementId) ?? 0) + 1);
    failureRef.current = null;
    schedule(elementId, delay);
    refreshState();
  }, [refreshState, schedule]);

  const cancelLocal = useCallback((elementId: number): void => {
    clearTimer(elementId);
    dirtyRef.current.delete(elementId);
    revisionsRef.current.delete(elementId);
    keysRef.current.delete(elementId);
    failureRef.current = null;
    refreshState();
  }, [clearTimer, refreshState]);

  const deleteElement = useCallback(async (element: EditorElement): Promise<boolean> => {
    if (element.id < 0) {
      cancelLocal(element.id);
      callbackRef.current.removeElement(element.id);
      return true;
    }
    clearTimer(element.id);
    pendingDeletesRef.current.set(element.id, element);
    dirtyRef.current.add(element.id);
    savingRef.current.add(element.id);
    failureRef.current = null;
    refreshState();
    try {
      let lease = await lockFor(element.id);
      try {
        await api.delete(element, lease.lock_token);
      } catch (error) {
        if (error instanceof ElementApiError && error.status === 423) {
          leasesRef.current.delete(element.id);
          lease = await lockFor(element.id, true);
          await api.delete(element, lease.lock_token);
        } else if (isNetworkError(error) && leaseIsFresh(lease)) {
          await api.delete(element, lease.lock_token);
        } else {
          throw error;
        }
      }
      callbackRef.current.removeElement(element.id);
      dirtyRef.current.delete(element.id);
      revisionsRef.current.delete(element.id);
      leasesRef.current.delete(element.id);
      pendingDeletesRef.current.delete(element.id);
      failureRef.current = null;
      return true;
    } catch (error) {
      failureRef.current = stateForError(error, navigator.onLine);
      return false;
    } finally {
      savingRef.current.delete(element.id);
      refreshState();
    }
  }, [api, cancelLocal, clearTimer, lockFor, refreshState]);

  const retry = useCallback((): void => {
    failureRef.current = null;
    for (const element of pendingDeletesRef.current.values()) {
      void deleteElement(element);
    }
    for (const elementId of dirtyRef.current) {
      if (!pendingDeletesRef.current.has(elementId)) schedule(elementId, 0);
    }
    refreshState();
  }, [deleteElement, refreshState, schedule]);

  useEffect(() => {
    const handleOffline = (): void => refreshState();
    const handleOnline = (): void => retry();
    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);
    const warnBeforeUnload = (event: BeforeUnloadEvent): void => {
      if (dirtyRef.current.size === 0) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', warnBeforeUnload);

    return () => {
      window.removeEventListener('offline', handleOffline);
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('beforeunload', warnBeforeUnload);
      for (const timer of timersRef.current.values()) window.clearTimeout(timer);
    };
  }, [refreshState, retry]);

  return { state, markDirty, cancelLocal, deleteElement, retry };
}
