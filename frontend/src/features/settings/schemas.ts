import { z } from 'zod';
import {
  email,
  newPassword,
  optionalPhone,
  optionalText,
  requiredText,
  withPasswordConfirmation,
} from '@/lib/forms/validation';

/** Field names match the API (UpdateMeRequest and friends). */

export const profileSchema = z.object({
  first_name: requiredText(80),
  last_name: requiredText(80),
  other_names: optionalText(120),
  phone: optionalPhone,
  alt_phone: optionalPhone,
  institution: optionalText(160),
  discipline: optionalText(160),
});
export type ProfileValues = z.input<typeof profileSchema>;

export const changePasswordSchema = withPasswordConfirmation(
  z.object({
    current_password: z.string().min(1, 'validation.required'),
    password: newPassword,
    password_confirmation: z.string(),
  }),
);
export type ChangePasswordValues = z.input<typeof changePasswordSchema>;

export const changeEmailSchema = z.object({
  email,
  password: z.string().min(1, 'validation.required'),
});
export type ChangeEmailValues = z.input<typeof changeEmailSchema>;

export const deleteAccountSchema = z.object({
  password: z.string().min(1, 'validation.required'),
});
export type DeleteAccountValues = z.input<typeof deleteAccountSchema>;
