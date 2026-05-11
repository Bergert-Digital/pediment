import { useEffect, useRef, useState } from '@wordpress/element';
import { getJob, JobStatus } from './useApiClient';

const POLL_MS = 750;

export type PollResult = {
  status: JobStatus['status'] | 'idle';
  urls: string[];
  progressNote: string | null;
  result: JobStatus['result'];
  error: string | null;
};

export default function useJobPolling(jobId: number | null): PollResult {
  const [state, setState] = useState<PollResult>({
    status: 'idle',
    urls: [],
    progressNote: null,
    result: null,
    error: null,
  });
  const timer = useRef<number | null>(null);

  useEffect(() => {
    if (jobId === null) {
      setState({ status: 'idle', urls: [], progressNote: null, result: null, error: null });
      return;
    }

    let cancelled = false;

    const tick = async () => {
      try {
        const job = await getJob(jobId);
        if (cancelled) { return; }
        setState({
          status: job.status,
          urls: job.urls_fetched ?? [],
          progressNote: job.progress_note,
          result: job.result,
          error: job.error,
        });
        if (job.status === 'complete' || job.status === 'error') {
          if (timer.current !== null) { window.clearInterval(timer.current); }
          return;
        }
      } catch (e: any) {
        if (cancelled) { return; }
        setState((s) => ({ ...s, status: 'error', error: e?.message ?? 'Polling failed' }));
        if (timer.current !== null) { window.clearInterval(timer.current); }
      }
    };

    tick();
    timer.current = window.setInterval(tick, POLL_MS);

    return () => {
      cancelled = true;
      if (timer.current !== null) { window.clearInterval(timer.current); }
    };
  }, [jobId]);

  return state;
}
