import { describe, expect, it } from 'vitest';
import { passwordStrength } from './passwordStrength';

describe('passwordStrength (spec 5.2 rules)', () => {
  it('needs at least 10 characters', () => {
    expect(passwordStrength('')).toMatchObject({ level: 'too_short', score: 0, meetsRules: false });
    expect(passwordStrength('abc12')).toMatchObject({ level: 'too_short', meetsRules: false });
  });

  it('needs a letter and a number', () => {
    expect(passwordStrength('abcdefghijkl')).toMatchObject({ level: 'weak', meetsRules: false });
    expect(passwordStrength('123456789012')).toMatchObject({ level: 'weak', meetsRules: false });
  });

  it('accepts 10+ characters with a letter and a number', () => {
    expect(passwordStrength('quietriver7')).toMatchObject({ level: 'fair', meetsRules: true });
  });

  it('rates longer, mixed passwords higher', () => {
    expect(passwordStrength('Quietriver77').score).toBe(3);
    expect(passwordStrength('Quiet-River-Lamp-2026').level).toBe('strong');
  });

  it('marks repetitive passwords down', () => {
    expect(passwordStrength('aaaaaaaaaaaaaaaaa1').level).toBe('fair');
  });

  it('rejects more than 72 bytes (bcrypt limit), counting multi-byte characters', () => {
    expect(passwordStrength('a1'.repeat(37)).level).toBe('too_long'); // 74 bytes
    // "é" is 2 bytes in UTF-8, so each "é1" is 3 bytes: 25 pairs = 50 characters but 75 bytes.
    expect(passwordStrength('é1'.repeat(25)).level).toBe('too_long');
    expect(passwordStrength('é1'.repeat(24)).meetsRules).toBe(true); // exactly 72 bytes
    expect(passwordStrength('a1'.repeat(36)).meetsRules).toBe(true); // exactly 72 bytes
  });
});
