import { api, type Session } from '@/lib/api/client';
import type { components, paths } from '@/lib/api/schema';

/** Auth endpoint calls (spec 5.2, contract tag "Auth"). Types come from openapi.yaml. */

type Json<T> = T extends { content: { 'application/json': infer Body } } ? Body : never;

export type LoginBody = components['schemas']['LoginRequest'];
export type RegisterBody = components['schemas']['RegisterRequest'];
export type SetPasswordBody = components['schemas']['SetPasswordRequest'];
export type ForgotPasswordBody = Json<paths['/auth/password/forgot']['post']['requestBody']>;
export type User = components['schemas']['User'];

type SessionResponse = components['schemas']['SessionResponse'];
type UserResponse = Json<paths['/auth/register']['post']['responses']['201']>;
type MessageResponse = components['schemas']['MessageResponse'];

export async function login(body: LoginBody): Promise<Session> {
  return (await api.post<SessionResponse>('/auth/login', body)).data.data;
}

/** 201 with the new user; no session (the user must verify, then log in). */
export async function register(body: RegisterBody): Promise<User> {
  return (await api.post<UserResponse>('/auth/register', body)).data.data.user;
}

export async function verifyEmail(token: string): Promise<User> {
  return (await api.post<UserResponse>('/auth/verify-email', { token })).data.data.user;
}

export async function resendVerification(): Promise<void> {
  await api.post('/auth/verify-email/resend');
}

/** Always 200 with "If that email exists..." (no account discovery). */
export async function forgotPassword(body: ForgotPasswordBody): Promise<string> {
  return (await api.post<MessageResponse>('/auth/password/forgot', body)).data.data.message;
}

/** 204; every session is signed out. */
export async function resetPassword(body: SetPasswordBody): Promise<void> {
  await api.post('/auth/password/reset', body);
}

export async function acceptInvite(body: SetPasswordBody): Promise<Session> {
  return (await api.post<SessionResponse>('/auth/invite/accept', body)).data.data;
}

/** Cookie endpoint: the API client already sends cookies and X-Requested-With. */
export async function linkGoogle(password: string): Promise<Session> {
  return (await api.post<SessionResponse>('/auth/google/link', { password })).data.data;
}

/** A full-page navigation, not a fetch (brief 3a). */
export const GOOGLE_SIGN_IN_URL = '/api/v1/auth/google/redirect';
