import { api } from '../api/client';
import type { components, paths } from '../api/schema';

export type Me = components['schemas']['Me'];
export type UpdateMeBody = components['schemas']['UpdateMeRequest'];
type MeResponse = paths['/me']['get']['responses']['200']['content']['application/json'];
type UpdateMeResponse = paths['/me']['patch']['responses']['200']['content']['application/json'];

export const ME_QUERY_KEY = ['me'] as const;

/** GET /me: works for pending_verification users too (brief 3a). */
export async function fetchMe(): Promise<Me> {
  const response = await api.get<MeResponse>('/me');
  return response.data.data;
}

/** PATCH /me: send only the fields that changed (brief 3a). */
export async function updateMe(body: UpdateMeBody): Promise<Me> {
  const response = await api.patch<UpdateMeResponse>('/me', body);
  return response.data.data;
}
