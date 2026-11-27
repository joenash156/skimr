import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { checkLocales, flattenKeys } from './i18n-check.mjs';

let dir;

function write(lang, file, data) {
  mkdirSync(join(dir, lang), { recursive: true });
  writeFileSync(join(dir, lang, file), JSON.stringify(data));
}

function setup() {
  dir = mkdtempSync(join(tmpdir(), 'i18n-check-'));
}

afterEach(() => {
  if (dir) rmSync(dir, { recursive: true, force: true });
});

describe('flattenKeys', () => {
  it('turns nested objects into dotted keys', () => {
    expect([...flattenKeys({ a: { b: 'x', c: { d: 'y' } }, e: 'z' }).keys()]).toEqual([
      'a.b',
      'a.c.d',
      'e',
    ]);
  });
});

describe('checkLocales', () => {
  it('passes when fr and es have every en key', () => {
    setup();
    const data = { shell: { title: 'x', items_one: 'a', items_other: 'b' } };
    write('en', 'common.json', data);
    write('fr', 'common.json', { shell: { ...data.shell, items_many: 'c' } });
    write('es', 'common.json', data);
    expect(checkLocales(dir)).toEqual({ errors: [], warnings: [] });
  });

  it('fails when a key is missing from fr or es', () => {
    setup();
    write('en', 'common.json', { a: 'x', b: { c: 'y' } });
    write('fr', 'common.json', { a: 'x' });
    write('es', 'common.json', { a: 'x', b: { c: 'y' } });
    expect(checkLocales(dir).errors).toEqual(['fr/common.json: missing "b.c"']);
  });

  it('fails when a whole namespace file is missing', () => {
    setup();
    write('en', 'auth.json', { a: 'x' });
    write('fr', 'auth.json', { a: 'x' });
    expect(checkLocales(dir).errors).toEqual(['es/auth.json is missing (exists in en)']);
  });

  it('fails on empty translations and warns on keys not in en', () => {
    setup();
    write('en', 'common.json', { a: 'x' });
    write('fr', 'common.json', { a: '' });
    write('es', 'common.json', { a: 'x', old: 'y' });
    const result = checkLocales(dir);
    expect(result.errors).toEqual(['fr/common.json: "a" must be a non-empty string']);
    expect(result.warnings).toEqual(['es/common.json: "old" is not in en (unused?)']);
  });
});
