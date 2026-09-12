import test from 'node:test';
import assert from 'node:assert/strict';
import { UploadQueue } from '../wp-content/plugins/manga-overlay-core/assets/admin/upload-queue.mjs';

test('upload queue naturally sorts files and never exceeds two in-flight requests', async () => {
  let sequence = 0, active = 0, peak = 0;
  const queue = new UploadQueue({ concurrency: 9, key: () => String(++sequence) });
  queue.add([{ name: '10.png' }, { name: '2.png' }, { name: '1.png' }]);
  assert.deepEqual(queue.jobs.map(job => job.file.name), ['1.png', '2.png', '10.png']);
  await queue.run(async job => {
    peak = Math.max(peak, ++active);
    await new Promise(resolve => setTimeout(resolve, job.key === '1' ? 20 : 1));
    active--;
    return { id: Number(job.key) };
  });
  assert.equal(peak, 2);
  assert.deepEqual(queue.jobs.map(job => job.result?.id), [1, 2, 3]);
  assert.equal(queue.running, false);
});

test('failed upload retries retain the same idempotency key and do not resend successes', async () => {
  let sequence = 0;
  const queue = new UploadQueue({ key: () => String(++sequence) });
  queue.add([{ name: '1.png' }, { name: '2.png' }]);
  const attempts: string[] = [];
  await queue.run(async job => {
    attempts.push(job.key);
    if (job.key === '2') throw new Error('connection lost after server acceptance');
    return { id: 1 };
  });
  assert.equal(queue.jobs[1].status, 'error');
  await queue.run(async job => { attempts.push(job.key); return { id: 2 }; }, true);
  assert.deepEqual(attempts, ['1', '2', '2']);
  assert.deepEqual(queue.jobs.map(job => job.status), ['done', 'done']);
});

test('manual queue order and a sequential upload-only queue are respected', async () => {
  let sequence = 0, active = 0;
  const queue = new UploadQueue({ concurrency: 1, key: () => String(++sequence) });
  queue.add([{ name: '1.png' }, { name: '2.png' }]);
  queue.move(1, 0);
  const order: string[] = [];
  await queue.run(async job => {
    assert.equal(++active, 1);
    assert.throws(() => queue.add([{ name: '3.png' }]));
    order.push(job.file.name);
    active--;
    return { id: Number(job.key) };
  });
  assert.deepEqual(order, ['2.png', '1.png']);
});
