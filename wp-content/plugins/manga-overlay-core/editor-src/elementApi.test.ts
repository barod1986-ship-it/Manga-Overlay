import { describe, expect, it } from 'vitest';
import { ElementApiError, createBody, endpointUrl, idempotencyKey, patchBody } from './elementApi';
import { saveState, stateForError } from './saveState';
import type { EditorElement } from './types';

const element: EditorElement = {
  id: 17,
  page_id: 41,
  target_lang: 'ar',
  element_type: 'bubble',
  x_unit: 100_000,
  y_unit: 200_000,
  w_unit: 300_000,
  h_unit: 150_000,
  rotation_mdeg: 0,
  z_index: 2,
  content: '</script>نص آمن',
  style: { shape: 'ellipse', color: '#111111' },
  version: 7,
};

describe('T-13 element REST and concurrency contract', () => {
  it('serializes create and patch without client-only identifiers', () => {
    expect(createBody(element)).toMatchObject({ page_id: 41, content: element.content });
    expect(createBody(element)).not.toHaveProperty('id');
    expect(createBody(element)).not.toHaveProperty('version');
    expect(patchBody(element)).toMatchObject({ element_type: 'bubble', style: element.style });
    expect(patchBody(element)).not.toHaveProperty('page_id');
    expect(patchBody(element)).not.toHaveProperty('target_lang');
  });

  it('keeps POST retry keys stable-sized and visible', () => {
    const key = idempotencyKey(-93);
    expect(key).toMatch(/^element-93-/);
    expect(key.length).toBeLessThanOrEqual(100);
    expect(key).not.toMatch(/[\u0000-\u001f\u007f]/);
  });

  it('supports both pretty and query-based WordPress REST roots', () => {
    expect(endpointUrl('/wp-json/mol/v1/', 'elements/7', 'https://example.test').toString())
      .toBe('https://example.test/wp-json/mol/v1/elements/7');
    expect(endpointUrl('/?rest_route=/mol/v1/', 'elements/7', 'https://example.test').searchParams.get('rest_route'))
      .toBe('/mol/v1/elements/7');
  });

  it('maps save states to the frozen Arabic labels', () => {
    expect(saveState('dirty').message).toBe('تغييرات غير محفوظة');
    expect(saveState('saving').message).toBe('جارٍ الحفظ');
    expect(saveState('saved').message).toBe('تم الحفظ');
    expect(stateForError(new ElementApiError(0, 'mol_network_error', 'offline'), false).kind).toBe('offline');
    expect(stateForError(new ElementApiError(423, 'mol_element_locked', 'locked'), true).kind).toBe('locked');
    expect(stateForError(new ElementApiError(412, 'mol_version_conflict', 'stale'), true).kind).toBe('conflict');
    expect(stateForError(new ElementApiError(428, 'mol_precondition_required', 'missing'), true).message)
      .toContain('If‑Match');
  });

  it('keeps structured error details for the lock owner label', () => {
    const error = new ElementApiError(423, 'mol_element_locked', 'locked', null, { locked_by: 'سارة' });
    expect(error.details.locked_by).toBe('سارة');
  });
});
