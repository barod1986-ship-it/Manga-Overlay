import test from 'node:test';
import assert from 'node:assert/strict';
import { EditorSession, SaveError, type PersistenceApi, type Lease, type CreateBody } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/persistence.ts';
import { createElement } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/drafts.ts';
import type { Element } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/state.ts';
const tick = () => new Promise<void>(resolve => setTimeout(resolve, 0));
const persisted = (body: CreateBody, version = 1): Element => ({ ...body, style: body.style ?? {}, id: 7, version, created_by: 1, updated_by: 1, created_at: '2026-09-07T00:00:00Z', updated_at: '2026-09-07T00:00:00Z' });
function fixture() {
  const initial = persisted({ ...createElement('bubble', [], 'x'), page_id: 1 });
  let server = structuredClone(initial), gate: (() => Promise<void>) | undefined, fail: Error | undefined;
  const writes: { method: string; body?: unknown; key?: string; version?: number }[] = [];
  const lease: Lease = { element_id: 7, user_id: 1, lock_token: 'token', expires_at: new Date(Date.now() + 45000).toISOString() };
  const api: PersistenceApi = {
    async create(body, key) { writes.push({ method: 'POST', body: structuredClone(body), key }); if (gate) await gate(); if (fail) throw fail; server = persisted(body); return structuredClone(server); },
    async patch(_id, body, version) { writes.push({ method: 'PATCH', body: structuredClone(body), version }); if (gate) await gate(); if (fail) throw fail; if (version !== server.version) throw new SaveError(412, 'conflict'); server = { ...server, ...body, version: version + 1 }; return structuredClone(server); },
    async remove() { writes.push({ method: 'DELETE' }); if (fail) throw fail; },
    async acquire() { return lease; }, async renew() { if (fail) throw fail; return lease; }, async release() {},
    async elements() { return [structuredClone(server)]; },
  };
  const session = new EditorSession(api, 60000);
  return { session, initial, writes, setGate: (value?: () => Promise<void>) => { gate = value; }, fail: (value?: Error) => { fail = value; }, server: () => server, replace: (patch: Partial<Element>) => { server = { ...server, ...patch }; } };
}

test('typing during an in-flight save preserves newer local content and advances confirmed versions only', async () => {
  const f = fixture(); try {
    f.session.observe(1, [f.initial]); f.session.select('7'); await tick();
    f.session.change('7', { content: 'first' });
    let release!: () => void; f.setGate(() => new Promise<void>(resolve => { release = resolve; }));
    const saving = f.session.save('7'); await tick();
    f.session.change('7', { content: 'second' }); assert.equal(f.session.state, 'saving');
    release(); await saving; f.setGate();
    assert.equal(f.session.records.get('7')!.value.content, 'second');
    assert.equal(f.session.records.get('7')!.value.source!.version, 2); assert.equal(f.session.dirty, true);
    await f.session.save('7'); assert.equal(f.server().content, 'second'); assert.equal(f.server().version, 3); assert.equal(f.session.dirty, false);
    assert.deepEqual(f.writes.map(write => write.version), [1, 2]);
  } finally { f.session.dispose(); }
});

test('ambiguous POST retries retain the exact payload and key, then save intervening edits with PATCH', async () => {
  const f = fixture(); try {
    f.session.add(1, createElement('bubble', [], 'draft:1')); f.fail(new TypeError('network'));
    await f.session.save('draft:1'); assert.equal(f.session.state, 'offline');
    f.session.change('draft:1', { content: 'typed after timeout' }); f.fail();
    await f.session.retry('draft:1');
    assert.deepEqual(f.writes[0], f.writes[1]); assert.equal(f.session.records.get('draft:1')!.value.content, 'typed after timeout');
    await f.session.save('draft:1'); assert.equal(f.writes[2].method, 'PATCH'); assert.equal(f.server().content, 'typed after timeout');
  } finally { f.session.dispose(); }
});

test('conflict requires a choice and reapplies only changed fields to the current server version', async () => {
  const f = fixture(); try {
    f.session.observe(1, [f.initial]); f.session.select('7'); await tick(); f.session.change('7', { content: 'mine' });
    f.replace({ content: 'theirs', x_unit: 220000, version: 2 });
    await f.session.save('7'); assert.equal(f.session.state, 'conflict'); assert.equal(f.session.editable('7'), false);
    await f.session.save('7'); assert.equal(f.writes.length, 1);
    f.session.resolve('7', true); await f.session.save('7');
    assert.equal(f.server().content, 'mine'); assert.equal(f.server().x_unit, 220000); assert.equal(f.server().version, 3);
  } finally { f.session.dispose(); }
});

test('network recovery checks version before retrying PATCH and never overwrites a newer revision', async () => {
  const f = fixture(); try {
    f.session.observe(1, [f.initial]); f.session.select('7'); await tick(); f.session.change('7', { content: 'mine' });
    f.fail(new TypeError('network')); await f.session.save('7'); f.fail(); f.replace({ version: 2, content: 'newer' });
    await f.session.retry('7'); assert.equal(f.session.state, 'conflict'); assert.equal(f.writes.length, 1);
    f.session.resolve('7', false); assert.equal(f.session.records.get('7')!.value.content, 'newer'); assert.equal(f.session.dirty, false);
  } finally { f.session.dispose(); }
});

test('deletion can be undone before sending and failed authorization stops all further saves', async () => {
  const f = fixture(); try {
    f.session.observe(1, [f.initial]); f.session.select('7'); await tick();
    f.session.delete('7'); assert.equal(f.session.elements(1).length, 0); assert.equal(f.session.undo('7'), true);
    assert.equal(f.session.elements(1).length, 1); await f.session.save('7'); assert.equal(f.writes.length, 0);
    f.session.change('7', { content: 'mine' }); f.fail(new SaveError(403, 'revoked')); await f.session.save('7');
    assert.equal(f.session.sessionError?.status, 403); await f.session.save('7'); assert.equal(f.writes.length, 1);
    assert.equal(f.session.records.get('7')!.value.content, 'mine');
  } finally { f.session.dispose(); }
});

test('lost leases block edits and writes until explicit reacquisition', async () => {
  const f = fixture(); try {
    f.session.observe(1, [f.initial]); f.session.select('7'); await tick(); f.session.change('7', { content: 'mine' });
    f.fail(new SaveError(423, 'other editor')); await f.session.save('7'); assert.equal(f.session.state, 'locked');
    f.session.change('7', { content: 'must not change' }); assert.equal(f.session.records.get('7')!.value.content, 'mine');
    await f.session.save('7'); assert.equal(f.writes.length, 1); f.fail(); await f.session.retry('7'); assert.equal(f.server().content, 'mine');
  } finally { f.session.dispose(); }
});

 test('style conflict recovery preserves unrelated properties and nested siblings from the latest version', async () => {
  const f = fixture(); try {
    f.session.observe(1, [f.initial]); f.session.select('7'); await tick();
    f.session.change('7', { style: { ...f.initial.style, fontWeight: 900 } });
    f.replace({ style: { ...f.initial.style, color: '#123456' }, version: 2 });
    await f.session.save('7'); f.session.resolve('7', true);
    assert.equal(f.session.records.get('7')!.value.style.color, '#123456');
    assert.equal(f.session.records.get('7')!.value.style.fontWeight, 900);
    assert.deepEqual((f.writes[0].body as any).style, { fontWeight: 900 });
  } finally { f.session.dispose(); }
});
