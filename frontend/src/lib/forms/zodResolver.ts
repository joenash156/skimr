import type { FieldError, FieldErrors, FieldValues, Resolver } from 'react-hook-form';
import type { z } from 'zod';

/**
 * Connects a zod schema to react-hook-form (spec 8: react-hook-form + zod),
 * without an extra adapter package. Messages in the schema are i18n keys,
 * e.g. 'validation.required'; `useFieldError` translates them.
 *
 * Forms here are flat (one level of fields), so issue paths are joined
 * with dots and used as field names directly.
 */
export function zodResolver<Schema extends z.ZodType<FieldValues, FieldValues>>(
  schema: Schema,
): Resolver<z.input<Schema>, unknown, z.output<Schema>> {
  return async (values) => {
    const result = await schema.safeParseAsync(values);
    if (result.success) {
      return { values: result.data, errors: {} };
    }
    const errors: Record<string, FieldError> = {};
    for (const issue of result.error.issues) {
      const name = issue.path.map(String).join('.');
      if (name && !errors[name]) {
        errors[name] = { type: issue.code, message: issue.message };
      }
    }
    return { values: {}, errors: errors as FieldErrors<z.input<Schema>> };
  };
}
