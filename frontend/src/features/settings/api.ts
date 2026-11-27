import { api } from '@/lib/api/client';
import type { components, paths } from '@/lib/api/schema';

/** Account endpoints used by Settings (contract tag "Me", P1). Types come from openapi.yaml. */

type Json<T> = T extends { content: { 'application/json': infer Body } } ? Body : never;

export type ChangePasswordBody = Json<paths['/me/password']['post']['requestBody']>;
export type ChangeEmailBody = Json<paths['/me/email']['post']['requestBody']>;
export type DeleteAccountBody = Json<NonNullable<paths['/me']['delete']['requestBody']>>;
type MessageResponse = components['schemas']['MessageResponse'];

/** 204; every session ends, this one included (brief 3a). */
export async function changePassword(body: ChangePasswordBody): Promise<void> {
  await api.post('/me/password', body);
}

/** 202 with a message; the change completes when the new address's link is opened. */
export async function changeEmail(body: ChangeEmailBody): Promise<string> {
  return (await api.post<MessageResponse>('/me/email', body)).data.data.message;
}

/** 204; 30-day soft delete, then purge (spec 5.3). Every session ends. */
export async function deleteAccount(body: DeleteAccountBody): Promise<void> {
  await api.delete('/me', { data: body });
}
