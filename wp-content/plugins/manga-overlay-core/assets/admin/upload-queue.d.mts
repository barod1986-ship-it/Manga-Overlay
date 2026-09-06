export interface UploadJob<TFile, TResult> {
  file: TFile;
  key: string;
  status: 'queued' | 'uploading' | 'done' | 'error';
  progress: number;
  result: TResult | null;
  error: string | null;
}

export class UploadQueue<TFile extends { name: string } = { name: string }, TResult = { id: number }> {
  constructor(options?: { concurrency?: number; key?: () => string; onChange?: () => void });
  concurrency: number;
  running: boolean;
  jobs: Array<UploadJob<TFile, TResult>>;
  add(files: Iterable<TFile>): void;
  move(from: number, to: number): void;
  run(worker: (job: UploadJob<TFile, TResult>, progress: (value: number) => void) => Promise<TResult>, retry?: boolean): Promise<Array<UploadJob<TFile, TResult>>>;
}
