import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { reapplyLocalChanges } from './concurrency';
import { ElementApi, ElementApiError, idempotencyKey } from './elementApi';
import { saveState as makeSaveState, stateForError } from './saveState';
import type {
  EditorBootstrap,
  EditorElement,
  ElementConflict,
  LockLease,
  SaveState,
} from './types';

const DEBOUNCE_MS = 1_200;
const RENEW_INTERVAL_MS = 15_000;

interface AutosaveCallbacks {
  readonly getElement: (id: number) => EditorElement | null;
  readonly replaceElement: (oldId: number, next: EditorElement) => void;
  readonly removeElement: (id: number) => void;
  readonly onIdChange: (oldId: number, nextId: number) => void;
}

interface AutosaveController {
  readonly state: SaveState;
  readonly conflict: ElementConflict | null;
  readonly markDirty: (elementId: number, delay?: number, baseline?: EditorElement) => void;
  readonly cancelLocal: (elementId: number) => void;
  readonly deleteElement: (element: EditorElement) => Promise<boolean>;
  readonly activateElement: (elementId: number | null) => void;
  readonly isSaving: (elementId: number) => boolean;
  readonly isReadOnly: (elementId: number) => boolean;
  readonly lockedBy: (elementId: number) => string | null;
  readonly retry: () => void;
  readonly useCurrentVersion: () => void;
  readonly reapplyConflict: () => void;
}

interface LockAccess {
  readonly status: 'acquiring' | 'owned' | 'locked';
  readonly lockedBy: string | null;
}

interface Failure {
  readonly elementId: number | null;
  readonly state: SaveState;
}

function isNetworkError(error: unknown): boolean {
  return error instanceof ElementApiError && error.status === 0;
}

function isLeaseLost(error: unknown): boolean {
  return error instanceof ElementApiError
    && ((error.status === 409 && error.code === 'mol_lock_lost') || error.status === 423);
}

function leaseIsFresh(lease: LockLease | undefined): lease is LockLease {
  return lease !== undefined && Date.parse(lease.expires_at) > Date.now() + 2_000;
}

function editorName(error: ElementApiError): string {
  const value = error.details.locked_by;
  return typeof value === 'string' && value.trim() !== '' ? value : 'محرر آخر';
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
  const baselinesRef = useRef(new Map<number, EditorElement>());
  const timersRef = useRef(new Map<number, number>());
  const keysRef = useRef(new Map<number, string>());
  const leasesRef = useRef(new Map<number, LockLease>());
  const lockRequestsRef = useRef(new Map<number, Promise<LockLease>>());
  const releaseRequestsRef = useRef(new Map<number, Promise<void>>());
  const accessRef = useRef(new Map<number, LockAccess>());
  const activeElementRef = useRef<number | null>(null);
  const pendingDeletesRef = useRef(new Map<number, EditorElement>());
  const failureRef = useRef<Failure | null>(null);
  const conflictRef = useRef<ElementConflict | null>(null);
  const [state, setState] = useState<SaveState>(() => makeSaveState('saved'));
  const [conflict, setConflict] = useState<ElementConflict | null>(null);
  const [, setAccessRevision] = useState(0);

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
      setState(failureRef.current.state);
      return;
    }
    setState(makeSaveState(dirtyRef.current.size > 0 ? 'dirty' : 'saved'));
  }, []);

  const updateAccess = useCallback((elementId: number, access: LockAccess | null): void => {
    if (access === null) accessRef.current.delete(elementId);
    else accessRef.current.set(elementId, access);
    setAccessRevision((revision) => revision + 1);
  }, []);

  const setFailure = useCallback((elementId: number | null, error: unknown): void => {
    if (error instanceof ElementApiError && error.status === 423 && elementId !== null) {
      const lockedBy = editorName(error);
      updateAccess(elementId, { status: 'locked', lockedBy });
      failureRef.current = {
        elementId,
        state: makeSaveState('locked', `العنصر قيد التحرير لدى ${lockedBy}`),
      };
    } else {
      failureRef.current = { elementId, state: stateForError(error, navigator.onLine) };
    }
    refreshState();
  }, [refreshState, updateAccess]);

  const clearTimer = useCallback((elementId: number): void => {
    const timer = timersRef.current.get(elementId);
    if (timer !== undefined) window.clearTimeout(timer);
    timersRef.current.delete(elementId);
  }, []);

  const lockFor = useCallback(async (elementId: number, force = false): Promise<LockLease> => {
    const releasing = releaseRequestsRef.current.get(elementId);
    if (releasing !== undefined) await releasing;

    const inFlight = lockRequestsRef.current.get(elementId);
    if (inFlight !== undefined) return inFlight;

    const cached = leasesRef.current.get(elementId);
    if (!force && leaseIsFresh(cached)) return cached;
    if (force) leasesRef.current.delete(elementId);
    updateAccess(elementId, { status: 'acquiring', lockedBy: null });

    const request = (async (): Promise<LockLease> => {
      let lease: LockLease;
      if (!force && cached !== undefined) {
        try {
          lease = await api.renewLock(elementId, cached.lock_token);
        } catch (error) {
          if (!isNetworkError(error)) throw error;
          lease = await api.renewLock(elementId, cached.lock_token);
        }
      } else {
        try {
          lease = await api.acquireLock(elementId);
        } catch (error) {
          if (!isNetworkError(error)) throw error;
          lease = await api.acquireLock(elementId);
        }
      }
      leasesRef.current.set(elementId, lease);
      updateAccess(elementId, { status: 'owned', lockedBy: null });

      return lease;
    })();
    lockRequestsRef.current.set(elementId, request);
    try {
      return await request;
    } finally {
      if (lockRequestsRef.current.get(elementId) === request) lockRequestsRef.current.delete(elementId);
    }
  }, [api, updateAccess]);

  const releaseIfIdle = useCallback((elementId: number): void => {
    if (elementId < 1
      || activeElementRef.current === elementId
      || dirtyRef.current.has(elementId)
      || savingRef.current.has(elementId)
      || pendingDeletesRef.current.has(elementId)
      || releaseRequestsRef.current.has(elementId)
    ) return;
    const lease = leasesRef.current.get(elementId);
    if (lease === undefined) {
      updateAccess(elementId, null);
      return;
    }
    leasesRef.current.delete(elementId);
    updateAccess(elementId, null);
    const request = api.releaseLock(elementId, lease.lock_token)
      .catch((error: unknown) => {
        if (!(error instanceof ElementApiError && (error.status === 404 || error.status === 409)) && !isNetworkError(error)) {
          setFailure(elementId, error);
        }
      })
      .finally(() => {
        if (releaseRequestsRef.current.get(elementId) === request) releaseRequestsRef.current.delete(elementId);
      });
    releaseRequestsRef.current.set(elementId, request);
  }, [api, setFailure, updateAccess]);

  const fetchCurrent = useCallback(async (element: EditorElement): Promise<EditorElement> => {
    const elements = await api.fetchPageElements(element.page_id, element.target_lang);
    const current = elements.find((candidate) => candidate.id === element.id);
    if (current === undefined) {
      throw new ElementApiError(404, 'mol_not_found', 'The element is no longer available.');
    }

    return current;
  }, [api]);

  const beginConflict = useCallback((
    element: EditorElement,
    current: EditorElement,
    operation: 'update' | 'delete',
  ): void => {
    clearTimer(element.id);
    const next: ElementConflict = {
      elementId: element.id,
      operation,
      baseline: baselinesRef.current.get(element.id) ?? element,
      yours: element,
      current,
    };
    conflictRef.current = next;
    setConflict(next);
    failureRef.current = { elementId: element.id, state: makeSaveState('conflict') };
    refreshState();
  }, [clearTimer, refreshState]);

  const currentOrConflict = useCallback(async (
    element: EditorElement,
    operation: 'update' | 'delete',
  ): Promise<EditorElement | null> => {
    const current = await fetchCurrent(element);
    if (current.version !== element.version) {
      beginConflict(element, current, operation);
      return null;
    }

    return current;
  }, [beginConflict, fetchCurrent]);

  const restoreLease = useCallback(async (
    element: EditorElement,
    operation: 'update' | 'delete',
  ): Promise<{ readonly element: EditorElement; readonly lease: LockLease } | null> => {
    leasesRef.current.delete(element.id);
    updateAccess(element.id, { status: 'acquiring', lockedBy: null });
    const current = await fetchCurrent(element);
    if (current.version !== element.version
      && (dirtyRef.current.has(element.id) || pendingDeletesRef.current.has(element.id))
    ) {
      beginConflict(element, current, operation);
      return null;
    }
    if (current.version !== element.version) callbackRef.current.replaceElement(element.id, current);
    const lease = await lockFor(element.id, true);

    return {
      element: current.version === element.version ? element : current,
      lease,
    };
  }, [beginConflict, fetchCurrent, lockFor, updateAccess]);

  const updatePersisted = useCallback(async (element: EditorElement): Promise<EditorElement | null> => {
    const finalAttempt = async (candidate: EditorElement, token: string): Promise<EditorElement | null> => {
      try {
        return await api.update(candidate, token);
      } catch (error) {
        if (error instanceof ElementApiError && error.status === 412) {
          beginConflict(element, await fetchCurrent(element), 'update');
          return null;
        }
        throw error;
      }
    };
    let lease: LockLease;
    try {
      lease = await lockFor(element.id);
    } catch (error) {
      if (!isLeaseLost(error)) throw error;
      const restored = await restoreLease(element, 'update');
      if (restored === null) return null;
      return finalAttempt(restored.element, restored.lease.lock_token);
    }

    try {
      return await api.update(element, lease.lock_token);
    } catch (error) {
      if (error instanceof ElementApiError && error.status === 412) {
        beginConflict(element, await fetchCurrent(element), 'update');
        return null;
      }
      if (error instanceof ElementApiError && error.status === 428) {
        const current = await currentOrConflict(element, 'update');
        if (current === null) return null;
        return finalAttempt({ ...element, version: current.version }, lease.lock_token);
      }
      if (isLeaseLost(error)) {
        const restored = await restoreLease(element, 'update');
        if (restored === null) return null;
        return finalAttempt(restored.element, restored.lease.lock_token);
      }
      if (isNetworkError(error) && leaseIsFresh(lease)) {
        return finalAttempt(element, lease.lock_token);
      }
      throw error;
    }
  }, [api, beginConflict, currentOrConflict, fetchCurrent, lockFor, restoreLease]);

  const deletePersisted = useCallback(async (element: EditorElement): Promise<boolean> => {
    const finalAttempt = async (candidate: EditorElement, token: string): Promise<boolean> => {
      try {
        await api.delete(candidate, token);
        return true;
      } catch (error) {
        if (error instanceof ElementApiError && error.status === 412) {
          beginConflict(element, await fetchCurrent(element), 'delete');
          return false;
        }
        throw error;
      }
    };
    let candidate = element;
    let lease: LockLease;
    try {
      lease = await lockFor(element.id);
    } catch (error) {
      if (!isLeaseLost(error)) throw error;
      const restored = await restoreLease(element, 'delete');
      if (restored === null) return false;
      candidate = restored.element;
      lease = restored.lease;
    }

    try {
      await api.delete(candidate, lease.lock_token);
      return true;
    } catch (error) {
      if (error instanceof ElementApiError && error.status === 412) {
        beginConflict(element, await fetchCurrent(element), 'delete');
        return false;
      }
      if (error instanceof ElementApiError && error.status === 428) {
        const current = await currentOrConflict(element, 'delete');
        if (current === null) return false;
        return finalAttempt(current, lease.lock_token);
      }
      if (isLeaseLost(error)) {
        const restored = await restoreLease(element, 'delete');
        if (restored === null) return false;
        return finalAttempt(restored.element, restored.lease.lock_token);
      }
      if (isNetworkError(error) && leaseIsFresh(lease)) {
        return finalAttempt(candidate, lease.lock_token);
      }
      throw error;
    }
  }, [api, beginConflict, currentOrConflict, fetchCurrent, lockFor, restoreLease]);

  const scheduleRef = useRef<(elementId: number, delay: number) => void>(() => undefined);
  const saveOne = useCallback(async (elementId: number): Promise<void> => {
    clearTimer(elementId);
    if (!navigator.onLine) {
      refreshState();
      return;
    }
    const element = callbackRef.current.getElement(elementId);
    if (element === null || !dirtyRef.current.has(elementId) || conflictRef.current?.elementId === elementId) return;
    const revision = revisionsRef.current.get(elementId) ?? 0;
    savingRef.current.add(elementId);
    failureRef.current = null;
    refreshState();

    try {
      let persisted: EditorElement | null;
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
        persisted = await updatePersisted(element);
      }
      if (persisted === null) return;

      const current = callbackRef.current.getElement(elementId);
      const currentRevision = revisionsRef.current.get(elementId) ?? revision;
      const nextId = persisted.id;
      if (current === null) {
        dirtyRef.current.delete(elementId);
        baselinesRef.current.delete(elementId);
      } else if (currentRevision === revision) {
        callbackRef.current.replaceElement(elementId, persisted);
        dirtyRef.current.delete(elementId);
        baselinesRef.current.delete(elementId);
      } else {
        const rebased = { ...current, id: nextId, version: persisted.version };
        callbackRef.current.replaceElement(elementId, rebased);
        dirtyRef.current.delete(elementId);
        dirtyRef.current.add(nextId);
        baselinesRef.current.delete(elementId);
        baselinesRef.current.set(nextId, persisted);
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
      setFailure(elementId, error);
    } finally {
      savingRef.current.delete(elementId);
      releaseIfIdle(elementId);
      refreshState();
    }
  }, [api, clearTimer, refreshState, releaseIfIdle, setFailure, updatePersisted]);

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

  const markDirty = useCallback((elementId: number, delay = DEBOUNCE_MS, baseline?: EditorElement): void => {
    if (baseline !== undefined && elementId > 0 && !baselinesRef.current.has(elementId)) {
      baselinesRef.current.set(elementId, baseline);
    }
    dirtyRef.current.add(elementId);
    revisionsRef.current.set(elementId, (revisionsRef.current.get(elementId) ?? 0) + 1);
    if (failureRef.current?.elementId === elementId) failureRef.current = null;
    schedule(elementId, delay);
    refreshState();
  }, [refreshState, schedule]);

  const cancelLocal = useCallback((elementId: number): void => {
    clearTimer(elementId);
    dirtyRef.current.delete(elementId);
    revisionsRef.current.delete(elementId);
    baselinesRef.current.delete(elementId);
    keysRef.current.delete(elementId);
    if (failureRef.current?.elementId === elementId) failureRef.current = null;
    refreshState();
  }, [clearTimer, refreshState]);

  const deleteElement = useCallback(async (element: EditorElement): Promise<boolean> => {
    if (element.id < 0) {
      cancelLocal(element.id);
      callbackRef.current.removeElement(element.id);
      return true;
    }
    clearTimer(element.id);
    if (!baselinesRef.current.has(element.id)) baselinesRef.current.set(element.id, element);
    pendingDeletesRef.current.set(element.id, element);
    dirtyRef.current.add(element.id);
    savingRef.current.add(element.id);
    failureRef.current = null;
    refreshState();
    try {
      if (!await deletePersisted(element)) return false;
      callbackRef.current.removeElement(element.id);
      dirtyRef.current.delete(element.id);
      revisionsRef.current.delete(element.id);
      baselinesRef.current.delete(element.id);
      leasesRef.current.delete(element.id);
      updateAccess(element.id, null);
      pendingDeletesRef.current.delete(element.id);
      failureRef.current = null;
      return true;
    } catch (error) {
      setFailure(element.id, error);
      return false;
    } finally {
      savingRef.current.delete(element.id);
      refreshState();
    }
  }, [cancelLocal, clearTimer, deletePersisted, refreshState, setFailure, updateAccess]);

  const activateElement = useCallback((elementId: number | null): void => {
    const previous = activeElementRef.current;
    activeElementRef.current = elementId;
    if (previous !== null && previous !== elementId) releaseIfIdle(previous);
    if (failureRef.current?.elementId === previous
      && failureRef.current.state.kind === 'locked'
      && !dirtyRef.current.has(previous ?? 0)
    ) failureRef.current = null;
    if (elementId === null || elementId < 1) {
      refreshState();
      return;
    }
    void lockFor(elementId)
      .then(() => {
        if (failureRef.current?.elementId === elementId && failureRef.current.state.kind === 'locked') {
          failureRef.current = null;
        }
        refreshState();
      })
      .catch((error: unknown) => setFailure(elementId, error));
  }, [lockFor, refreshState, releaseIfIdle, setFailure]);

  const retry = useCallback((): void => {
    const active = activeElementRef.current;
    if (failureRef.current?.state.kind === 'locked' && active !== null && active > 0) {
      failureRef.current = null;
      leasesRef.current.delete(active);
      void lockFor(active, true)
        .then(() => {
          if (dirtyRef.current.has(active)) schedule(active, 0);
          refreshState();
        })
        .catch((error: unknown) => setFailure(active, error));
      return;
    }
    failureRef.current = null;
    for (const element of pendingDeletesRef.current.values()) {
      void deleteElement(element);
    }
    for (const elementId of dirtyRef.current) {
      if (!pendingDeletesRef.current.has(elementId) && conflictRef.current?.elementId !== elementId) {
        schedule(elementId, 0);
      }
    }
    refreshState();
  }, [deleteElement, lockFor, refreshState, schedule, setFailure]);

  const useCurrentVersion = useCallback((): void => {
    const currentConflict = conflictRef.current;
    if (currentConflict === null) return;
    callbackRef.current.replaceElement(currentConflict.elementId, currentConflict.current);
    clearTimer(currentConflict.elementId);
    dirtyRef.current.delete(currentConflict.elementId);
    revisionsRef.current.delete(currentConflict.elementId);
    baselinesRef.current.delete(currentConflict.elementId);
    pendingDeletesRef.current.delete(currentConflict.elementId);
    conflictRef.current = null;
    setConflict(null);
    failureRef.current = null;
    refreshState();
  }, [clearTimer, refreshState]);

  const reapplyConflict = useCallback((): void => {
    const currentConflict = conflictRef.current;
    if (currentConflict === null) return;
    conflictRef.current = null;
    setConflict(null);
    failureRef.current = null;
    if (currentConflict.operation === 'delete') {
      pendingDeletesRef.current.delete(currentConflict.elementId);
      dirtyRef.current.delete(currentConflict.elementId);
      void deleteElement(currentConflict.current);
      return;
    }
    const rebased = reapplyLocalChanges(
      currentConflict.baseline,
      currentConflict.yours,
      currentConflict.current,
    );
    callbackRef.current.replaceElement(currentConflict.elementId, rebased);
    baselinesRef.current.set(currentConflict.elementId, currentConflict.current);
    dirtyRef.current.add(currentConflict.elementId);
    revisionsRef.current.set(
      currentConflict.elementId,
      (revisionsRef.current.get(currentConflict.elementId) ?? 0) + 1,
    );
    schedule(currentConflict.elementId, 0);
    refreshState();
  }, [deleteElement, refreshState, schedule]);

  const isSaving = useCallback((elementId: number): boolean => savingRef.current.has(elementId), []);
  const isReadOnly = useCallback((elementId: number): boolean => {
    if (elementId < 1) return false;
    if (conflictRef.current?.elementId === elementId) return true;
    return accessRef.current.get(elementId)?.status !== 'owned';
  }, []);
  const lockedBy = useCallback((elementId: number): string | null => {
    const access = accessRef.current.get(elementId);
    return access?.status === 'locked' ? access.lockedBy : null;
  }, []);

  useEffect(() => {
    const renewActiveLeases = (): void => {
      for (const [elementId, lease] of leasesRef.current) {
        if (activeElementRef.current !== elementId
          && !dirtyRef.current.has(elementId)
          && !savingRef.current.has(elementId)
        ) {
          releaseIfIdle(elementId);
          continue;
        }
        void api.renewLock(elementId, lease.lock_token)
          .then((renewed) => {
            if (leasesRef.current.get(elementId)?.lock_token === lease.lock_token) {
              leasesRef.current.set(elementId, renewed);
              updateAccess(elementId, { status: 'owned', lockedBy: null });
            }
          })
          .catch(async (error: unknown) => {
            if (leasesRef.current.get(elementId)?.lock_token !== lease.lock_token) return;
            if (error instanceof ElementApiError && error.status === 409 && error.code === 'mol_lock_lost') {
              const element = callbackRef.current.getElement(elementId);
              if (element === null) return;
              try {
                const restored = await restoreLease(element, pendingDeletesRef.current.has(elementId) ? 'delete' : 'update');
                if (restored !== null && dirtyRef.current.has(elementId)) schedule(elementId, 0);
              } catch (restoreError) {
                setFailure(elementId, restoreError);
              }
              return;
            }
            if (!isNetworkError(error) || Date.parse(lease.expires_at) <= Date.now()) {
              setFailure(elementId, error);
            }
          });
      }
    };
    const interval = window.setInterval(renewActiveLeases, RENEW_INTERVAL_MS);
    return () => window.clearInterval(interval);
  }, [api, releaseIfIdle, restoreLease, schedule, setFailure, updateAccess]);

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

  return {
    state,
    conflict,
    markDirty,
    cancelLocal,
    deleteElement,
    activateElement,
    isSaving,
    isReadOnly,
    lockedBy,
    retry,
    useCurrentVersion,
    reapplyConflict,
  };
}
