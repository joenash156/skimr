/**
 * Password rules (spec 5.2): 10 to 72 characters, at least one letter and one
 * number. The 10,000-common-passwords check happens on the server, which
 * answers with a `fields.password` message.
 */
export const PASSWORD_MIN = 10;
export const PASSWORD_MAX = 72;

export type StrengthLevel = 'too_short' | 'weak' | 'fair' | 'good' | 'strong' | 'too_long';

export interface PasswordStrength {
  level: StrengthLevel;
  /** 0-4 bars filled. */
  score: 0 | 1 | 2 | 3 | 4;
  /** True when the spec's rules are met (the server may still reject a common password). */
  meetsRules: boolean;
}

const LETTER = /\p{L}/u;
const DIGIT = /\p{N}/u;

/** bcrypt ignores bytes past 72, so length is counted in UTF-8 bytes. */
function byteLength(value: string): number {
  return new TextEncoder().encode(value).length;
}

export function passwordStrength(password: string): PasswordStrength {
  if (byteLength(password) > PASSWORD_MAX)
    return { level: 'too_long', score: 0, meetsRules: false };
  const length = [...password].length;
  if (length < PASSWORD_MIN) {
    return { level: 'too_short', score: length === 0 ? 0 : 1, meetsRules: false };
  }

  const hasLetter = LETTER.test(password);
  const hasDigit = DIGIT.test(password);
  if (!hasLetter || !hasDigit) return { level: 'weak', score: 1, meetsRules: false };

  const hasMixedCase = /\p{Ll}/u.test(password) && /\p{Lu}/u.test(password);
  const hasSymbol = /[^\p{L}\p{N}]/u.test(password);
  const uniqueRatio = new Set(password).size / length;

  let points = 0;
  if (length >= 14) points += 1;
  if (length >= 18) points += 1;
  if (hasMixedCase) points += 1;
  if (hasSymbol) points += 1;
  if (uniqueRatio < 0.5) points -= 2; // e.g. "aaaaaaaaa1"

  if (points >= 3) return { level: 'strong', score: 4, meetsRules: true };
  if (points >= 1) return { level: 'good', score: 3, meetsRules: true };
  return { level: 'fair', score: 2, meetsRules: true };
}
