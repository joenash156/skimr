// pnpm i18n:check (spec 13): fails when a key exists in `en` but is missing
// from `fr` or `es`. English is the source language; every namespace file in
// src/locales/en/ must exist in the other languages with the same keys.
import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const SOURCE = 'en';
export const TARGETS = ['fr', 'es'];

/** Flattens {"a":{"b":"x"}} into ["a.b"]; non-string leaves are reported. */
export function flattenKeys(value, prefix = '', out = new Map()) {
  for (const [key, child] of Object.entries(value)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (child !== null && typeof child === 'object' && !Array.isArray(child)) {
      flattenKeys(child, path, out);
    } else {
      out.set(path, child);
    }
  }
  return out;
}

function readJson(file, errors) {
  try {
    return JSON.parse(readFileSync(file, 'utf8'));
  } catch (e) {
    errors.push(`${file}: invalid JSON (${e.message})`);
    return null;
  }
}

/** Returns { errors, warnings } for a locales directory. Pure apart from reading files. */
export function checkLocales(localesDir) {
  const errors = [];
  const warnings = [];
  const sourceDir = join(localesDir, SOURCE);

  if (!existsSync(sourceDir)) {
    errors.push(`${sourceDir} does not exist`);
    return { errors, warnings };
  }

  const namespaces = readdirSync(sourceDir).filter((f) => f.endsWith('.json'));
  for (const file of namespaces) {
    const source = readJson(join(sourceDir, file), errors);
    if (!source) continue;
    const sourceKeys = flattenKeys(source);

    for (const [key, value] of sourceKeys) {
      if (typeof value !== 'string' || value.trim() === '') {
        errors.push(`${SOURCE}/${file}: "${key}" must be a non-empty string`);
      }
    }

    for (const lang of TARGETS) {
      const target = join(localesDir, lang, file);
      if (!existsSync(target)) {
        errors.push(`${lang}/${file} is missing (exists in ${SOURCE})`);
        continue;
      }
      const data = readJson(target, errors);
      if (!data) continue;
      const targetKeys = flattenKeys(data);

      for (const key of sourceKeys.keys()) {
        const value = targetKeys.get(key);
        if (value === undefined) {
          errors.push(`${lang}/${file}: missing "${key}"`);
        } else if (typeof value !== 'string' || value.trim() === '') {
          errors.push(`${lang}/${file}: "${key}" must be a non-empty string`);
        }
      }
      for (const key of targetKeys.keys()) {
        // French and Spanish may need extra plural forms (e.g. _many) that English lacks.
        const isExtraPlural = /_(zero|two|few|many)$/.test(key);
        if (!sourceKeys.has(key) && !isExtraPlural) {
          warnings.push(`${lang}/${file}: "${key}" is not in ${SOURCE} (unused?)`);
        }
      }
    }
  }

  return { errors, warnings };
}

const isCli = process.argv[1] && pathToFileURL(process.argv[1]).href === import.meta.url;
if (isCli) {
  const localesDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'locales');
  const { errors, warnings } = checkLocales(localesDir);
  for (const w of warnings) console.warn(`warning: ${w}`);
  for (const e of errors) console.error(`error: ${e}`);
  if (errors.length > 0) {
    console.error(`\ni18n:check failed with ${errors.length} error(s).`);
    process.exit(1);
  }
  console.log('i18n:check passed: fr and es have every en key.');
}
