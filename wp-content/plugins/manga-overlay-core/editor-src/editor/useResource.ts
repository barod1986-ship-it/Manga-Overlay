import { useEffect, useState } from 'react';
import { EditorError } from './api';

type Resource<T> = { key: string | null; status: 'loading' } | { key: string; status: 'ready'; data: T } | { key: string; status: 'error'; error: EditorError };

/** Hide stale content immediately and cancel the previous request on navigation/retry. */
export function useResource<T>(key: string | null, load: (signal: AbortSignal) => Promise<T>): Resource<T> {
  const [state, setState] = useState<Resource<T>>({ key, status: 'loading' });
  useEffect(() => {
    if (key === null) return;
    const controller = new AbortController();
    setState({ key, status: 'loading' });
    void load(controller.signal).then(data => {
      if (!controller.signal.aborted) setState({ key, status: 'ready', data });
    }, error => {
      if (!controller.signal.aborted) setState({ key, status: 'error', error: error instanceof EditorError ? error : new EditorError(0) });
    });
    return () => controller.abort();
  }, [key, load]);
  return key === state.key ? state : { key, status: 'loading' };
}
