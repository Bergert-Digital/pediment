import apiFetch from '@wordpress/api-fetch';

export type JobResponse = { job_id: number };
export type JobStatus = {
  status: 'queued' | 'composing' | 'complete' | 'error';
  urls_fetched: string[];
  progress_note: string | null;
  result: { blocks: any[]; urls_fetched: string[] } | null;
  error: string | null;
};
export type RefineResponse = { attributes: Record<string, any>; innerBlocks: any[] };

export async function postCompose(body: { prompt: string; page_type: string; tone: string }) {
  return apiFetch<JobResponse>({ path: '/starter-ai/v1/compose', method: 'POST', data: body });
}
export async function postEdit(body: { instruction: string; tree: any[] }) {
  return apiFetch<JobResponse>({ path: '/starter-ai/v1/edit', method: 'POST', data: body });
}
export async function postRefine(body: { blockName: string; attributes: any; innerBlocks: any[]; instruction: string }) {
  return apiFetch<RefineResponse>({ path: '/starter-ai/v1/refine', method: 'POST', data: body });
}
export async function getJob(id: number) {
  return apiFetch<JobStatus>({ path: `/starter-ai/v1/jobs/${id}`, method: 'GET' });
}
