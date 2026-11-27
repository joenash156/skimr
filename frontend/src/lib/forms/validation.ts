import { z } from 'zod';
import { passwordStrength } from '../passwordStrength';

/**
 * Shared field rules, mirroring the API contract (openapi.yaml) and spec 5.2.
 * Messages are i18n keys in the `common` namespace (validation.*).
 */

export const requiredText = (max: number) =>
  z.string().trim().min(1, 'validation.required').max(max, 'validation.too_long');

export const optionalText = (max: number) => z.string().trim().max(max, 'validation.too_long');

export const email = z
  .string()
  .trim()
  .min(1, 'validation.required')
  .max(254, 'validation.too_long')
  .pipe(z.email('validation.email'));

/** E.164, e.g. +233241234567 (contract: Phone). Empty means "not given". */
export const E164 = /^\+[1-9][0-9]{6,14}$/;
export const optionalPhone = z
  .string()
  .trim()
  .refine((value) => value === '' || E164.test(value), 'validation.phone');

/** 10-72 bytes, at least one letter and one number (spec 5.2). */
export const newPassword = z.string().superRefine((value, ctx) => {
  if (value === '') {
    ctx.addIssue({ code: 'custom', message: 'validation.required' });
    return;
  }
  const { level } = passwordStrength(value);
  if (level === 'too_short') {
    ctx.addIssue({ code: 'custom', message: 'validation.password_too_short' });
  } else if (level === 'too_long') {
    ctx.addIssue({ code: 'custom', message: 'validation.password_too_long' });
  } else if (level === 'weak') {
    ctx.addIssue({ code: 'custom', message: 'validation.password_letter_number' });
  }
});

/** Adds "passwords match" to a schema with password + password_confirmation. */
export function withPasswordConfirmation<
  T extends z.ZodObject<{ password: z.ZodType<string>; password_confirmation: z.ZodType<string> }>,
>(schema: T) {
  return schema.refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'validation.password_mismatch',
  });
}
