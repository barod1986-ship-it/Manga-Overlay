import type {
  EditorBootstrap,
  EditorElement,
  ElementType,
  LockLease,
  PresetScope,
  StylePreset,
} from './types';

interface ElementResponse {
  readonly data: EditorElement;
  readonly meta: Readonly<Record<string, unknown>>;
}

interface LockResponse {
  readonly data: LockLease;
  readonly meta: Readonly<Record<string, unknown>>;
}

interface ElementListResponse {
  readonly data: readonly EditorElement[];
  readonly meta: Readonly<Record<string, unknown>>;
}

interface PresetResponse {
  readonly data: StylePreset;
  readonly meta: Readonly<Record<string, unknown>>;
}

interface PresetListResponse {
  readonly data: readonly StylePreset[];
  readonly meta: { readonly count: number };
}

interface ErrorResponse {
  readonly code?: unknown;
  readonly message?: unknown;
  readonly data?: unknown;
}

export class ElementApiError extends Error {
  public constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly retryAfter: number | null = null,
    public readonly details: Readonly<Record<string, unknown>> = {},
  ) {
    super(message);
    this.name = 'ElementApiError';
  }
}

export function createBody(element: EditorElement): Readonly<Record<string, unknown>> {
  return {
    page_id: element.page_id,
    target_lang: element.target_lang,
    element_type: element.element_type,
    x_unit: element.x_unit,
    y_unit: element.y_unit,
    w_unit: element.w_unit,
    h_unit: element.h_unit,
    rotation_mdeg: element.rotation_mdeg,
    z_index: element.z_index,
    content: element.content,
    style: element.style,
  };
}

export function patchBody(element: EditorElement): Readonly<Record<string, unknown>> {
  return {
    element_type: element.element_type,
    x_unit: element.x_unit,
    y_unit: element.y_unit,
    w_unit: element.w_unit,
    h_unit: element.h_unit,
    rotation_mdeg: element.rotation_mdeg,
    z_index: element.z_index,
    content: element.content,
    style: element.style,
  };
}

export class ElementApi {
  private readonly root: URL;

  public constructor(private readonly config: EditorBootstrap['api']) {
    this.root = new URL(config.root, window.location.origin);
  }

  public async create(element: EditorElement, idempotencyKey: string): Promise<EditorElement> {
    const response = await this.request<ElementResponse>('elements', {
      method: 'POST',
      headers: { 'MOL-Idempotency-Key': idempotencyKey },
      body: createBody(element),
    });

    return response.data;
  }

  public async acquireLock(elementId: number): Promise<LockLease> {
    const response = await this.request<LockResponse>(`elements/${elementId}/lock`, { method: 'POST' });

    return response.data;
  }

  public async renewLock(elementId: number, lockToken: string): Promise<LockLease> {
    const response = await this.request<LockResponse>(`elements/${elementId}/lock`, {
      method: 'PUT',
      headers: { 'X-MOL-Lock-Token': lockToken },
    });

    return response.data;
  }

  public async releaseLock(elementId: number, lockToken: string): Promise<void> {
    await this.request<null>(`elements/${elementId}/lock`, {
      method: 'DELETE',
      headers: { 'X-MOL-Lock-Token': lockToken },
    });
  }

  public async fetchPageElements(pageId: number, targetLanguage: string): Promise<readonly EditorElement[]> {
    const response = await this.request<ElementListResponse>(`pages/${pageId}/elements`, {
      method: 'GET',
      query: { lang: targetLanguage },
    });

    return response.data;
  }

  public async update(element: EditorElement, lockToken: string): Promise<EditorElement> {
    const response = await this.request<ElementResponse>(`elements/${element.id}`, {
      method: 'PATCH',
      headers: {
        'If-Match': `"${element.version}"`,
        'X-MOL-Lock-Token': lockToken,
      },
      body: patchBody(element),
    });

    return response.data;
  }

  public async delete(element: EditorElement, lockToken: string): Promise<void> {
    await this.request<null>(`elements/${element.id}`, {
      method: 'DELETE',
      headers: {
        'If-Match': `"${element.version}"`,
        'X-MOL-Lock-Token': lockToken,
      },
    });
  }

  public async listPresets(workId: number, elementType: ElementType): Promise<readonly StylePreset[]> {
    const response = await this.request<PresetListResponse>('presets', {
      method: 'GET',
      query: { work_id: String(workId), type: elementType },
    });

    return response.data;
  }

  public async createPreset(input: {
    readonly scope: PresetScope;
    readonly work_id: number | null;
    readonly name: string;
    readonly element_type: ElementType;
    readonly style: Readonly<Record<string, unknown>>;
    readonly is_default: boolean;
  }): Promise<StylePreset> {
    const response = await this.request<PresetResponse>('presets', {
      method: 'POST',
      body: input,
    });

    return response.data;
  }

  public async updatePreset(
    presetId: number,
    patch: Readonly<{ name?: string; style?: Readonly<Record<string, unknown>>; is_default?: boolean }>,
  ): Promise<StylePreset> {
    const response = await this.request<PresetResponse>(`presets/${presetId}`, {
      method: 'PATCH',
      body: patch,
    });

    return response.data;
  }

  public async deletePreset(presetId: number): Promise<void> {
    await this.request<null>(`presets/${presetId}`, { method: 'DELETE' });
  }

  private async request<T>(
    path: string,
    options: {
      readonly method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
      readonly headers?: Readonly<Record<string, string>>;
      readonly body?: Readonly<Record<string, unknown>>;
      readonly query?: Readonly<Record<string, string>>;
    },
  ): Promise<T> {
    let response: Response;
    try {
      const url = endpointUrl(this.root.toString(), path, window.location.origin);
      for (const [name, value] of Object.entries(options.query ?? {})) url.searchParams.set(name, value);
      response = await fetch(url, {
        method: options.method,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'X-WP-Nonce': this.config.nonce,
          ...(options.body === undefined ? {} : { 'Content-Type': 'application/json' }),
          ...options.headers,
        },
        ...(options.body === undefined ? {} : { body: JSON.stringify(options.body) }),
      });
    } catch (error) {
      throw new ElementApiError(
        0,
        'mol_network_error',
        error instanceof Error ? error.message : 'Network request failed.',
      );
    }

    if (response.status === 204) return null as T;
    let body: unknown;
    try {
      body = await response.json();
    } catch {
      body = null;
    }
    if (!response.ok) {
      const error = body !== null && typeof body === 'object' ? body as ErrorResponse : {};
      const details = error.data !== null && typeof error.data === 'object'
        ? error.data as Readonly<Record<string, unknown>>
        : {};
      const retryAfter = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);
      throw new ElementApiError(
        response.status,
        typeof error.code === 'string' ? error.code : 'mol_request_failed',
        typeof error.message === 'string' ? error.message : 'The save request failed.',
        Number.isFinite(retryAfter) ? retryAfter : null,
        details,
      );
    }

    return body as T;
  }
}

export function endpointUrl(root: string, path: string, origin: string): URL {
  const base = new URL(root, origin);
  const restRoute = base.searchParams.get('rest_route');
  if (restRoute !== null) {
    base.searchParams.set('rest_route', `${restRoute.replace(/\/?$/, '/')}${path}`);
    return base;
  }

  return new URL(path, base);
}

export function idempotencyKey(localId: number): string {
  const random = typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  return `element-${Math.abs(localId)}-${random}`.slice(0, 100);
}
