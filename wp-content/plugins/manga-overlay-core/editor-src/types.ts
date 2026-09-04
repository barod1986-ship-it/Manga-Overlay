export type ElementType = 'bubble' | 'narration' | 'free_text' | 'sfx';

export interface EditorImage {
  readonly attachment_id: number;
  readonly url: string;
  readonly width: number;
  readonly height: number;
  readonly srcset: string | null;
  readonly sizes: string | null;
  readonly alt: string | null;
}

export interface EditorElement {
  readonly id: number;
  readonly page_id: number;
  readonly target_lang: string;
  readonly element_type: ElementType;
  readonly x_unit: number;
  readonly y_unit: number;
  readonly w_unit: number;
  readonly h_unit: number;
  readonly rotation_mdeg: number;
  readonly z_index: number;
  readonly content: string;
  readonly style: Readonly<Record<string, unknown>>;
  readonly version: number;
}

export interface EditorPageData {
  readonly id: number;
  readonly chapter_id: number;
  readonly page_index: number;
  readonly natural_width: number;
  readonly natural_height: number;
  readonly image: EditorImage;
  readonly elements: readonly EditorElement[];
}

export interface EditorBootstrap {
  readonly work: {
    readonly id: number;
    readonly slug: string;
    readonly title: string;
    readonly status: string;
  };
  readonly chapter: {
    readonly id: number;
    readonly chapter_label: string;
    readonly title: string | null;
    readonly slug: string;
    readonly translation_status: string;
    readonly is_published: boolean;
  };
  readonly pages: readonly EditorPageData[];
  readonly targetLanguage: string;
  readonly links: {
    readonly work: string;
    readonly reader: string | null;
  };
  readonly release: {
    readonly core: string;
  };
}

export interface EditorState {
  readonly pagePosition: number;
  readonly selectedElementId: number | null;
  readonly zoom: number;
  readonly preview: boolean;
  readonly layersCollapsed: boolean;
}

export type EditorAction =
  | { readonly type: 'go-page'; readonly position: number; readonly pageCount: number }
  | { readonly type: 'select-element'; readonly id: number | null }
  | { readonly type: 'set-zoom'; readonly zoom: number }
  | { readonly type: 'toggle-preview' }
  | { readonly type: 'toggle-layers' };
