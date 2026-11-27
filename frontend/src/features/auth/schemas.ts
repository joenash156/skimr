import { z } from 'zod';
import {
  email,
  newPassword,
  optionalPhone,
  optionalText,
  requiredText,
  withPasswordConfirmation,
} from '@/lib/forms/validation';

/** Form schemas for the auth screens; field names match the API (snake_case). */

export const loginSchema = z.object({
  email,
  password: z.string().min(1, 'validation.required'),
});
export type LoginValues = z.input<typeof loginSchema>;

export const registerSchema = withPasswordConfirmation(
  z.object({
    first_name: requiredText(80),
    last_name: requiredText(80),
    other_names: optionalText(120),
    email,
    phone: optionalPhone,
    password: newPassword,
    password_confirmation: z.string(),
    accept_terms: z.boolean().refine((value) => value, 'validation.accept_terms'),
  }),
);
export type RegisterValues = z.input<typeof registerSchema>;

export const forgotPasswordSchema = z.object({ email });
export type ForgotPasswordValues = z.input<typeof forgotPasswordSchema>;

export const setPasswordSchema = withPasswordConfirmation(
  z.object({ password: newPassword, password_confirmation: z.string() }),
);
export type SetPasswordValues = z.input<typeof setPasswordSchema>;

export const googleLinkSchema = z.object({ password: z.string().min(1, 'validation.required') });
export type GoogleLinkValues = z.input<typeof googleLinkSchema>;

export const completeProfileSchema = z.object({
  first_name: requiredText(80),
  last_name: requiredText(80),
  other_names: optionalText(120),
  phone: optionalPhone,
});
export type CompleteProfileValues = z.input<typeof completeProfileSchema>;
