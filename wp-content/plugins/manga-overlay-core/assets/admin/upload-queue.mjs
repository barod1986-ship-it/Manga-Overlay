export class UploadQueue {
  constructor({ concurrency = 2, key = () => crypto.randomUUID(), onChange = () => {} } = {}) {
    this.concurrency = Math.min(2, Math.max(1, concurrency));
    this.key = key;
    this.onChange = onChange;
    this.jobs = [];
    this.running = false;
  }

  add(files) {
    if (this.running) throw new Error('The upload queue is running.');
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    for (const file of [...files].sort((a, b) => collator.compare(a.name, b.name))) {
      this.jobs.push({ file, key: this.key(), status: 'queued', progress: 0, result: null, error: null });
    }
    this.onChange();
  }

  move(from, to) {
    if (this.running || from < 0 || to < 0 || from >= this.jobs.length || to >= this.jobs.length) return;
    this.jobs.splice(to, 0, this.jobs.splice(from, 1)[0]);
    this.onChange();
  }

  async run(worker, retry = false) {
    if (this.running) throw new Error('The upload queue is running.');
    if (retry) for (const job of this.jobs) if (job.status === 'error') job.status = 'queued';
    this.running = true;
    this.onChange();
    const consume = async () => {
      for (;;) {
        const job = this.jobs.find(item => item.status === 'queued');
        if (!job) return;
        job.status = 'uploading';
        job.error = null;
        this.onChange();
        try {
          job.result = await worker(job, progress => {
            job.progress = progress;
            this.onChange();
          });
          job.status = 'done';
          job.progress = 100;
        } catch (error) {
          job.status = 'error';
          job.error = error instanceof Error ? error.message : 'تعذر رفع الصورة.';
        }
        this.onChange();
      }
    };
    try {
      await Promise.all(Array.from({ length: this.concurrency }, consume));
      return this.jobs;
    } finally {
      this.running = false;
      this.onChange();
    }
  }
}
