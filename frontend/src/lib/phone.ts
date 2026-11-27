/**
 * Country calling codes for the phone picker (spec 5.2: E.164 with a country
 * picker, default Ghana +233). Names come from Intl.DisplayNames in the UI
 * language, so no country name is hard-coded.
 */
export const DEFAULT_COUNTRY = 'GH';

export const COUNTRY_CALLING_CODES: Readonly<Record<string, string>> = {
  // West Africa first: most of our users.
  GH: '233',
  NG: '234',
  CI: '225',
  SN: '221',
  TG: '228',
  BJ: '229',
  BF: '226',
  ML: '223',
  NE: '227',
  GN: '224',
  SL: '232',
  LR: '231',
  GM: '220',
  GW: '245',
  CV: '238',
  MR: '222',
  // Rest of Africa
  CM: '237',
  KE: '254',
  UG: '256',
  TZ: '255',
  RW: '250',
  BI: '257',
  ET: '251',
  SO: '252',
  DJ: '253',
  ER: '291',
  SD: '249',
  SS: '211',
  EG: '20',
  LY: '218',
  TN: '216',
  DZ: '213',
  MA: '212',
  ZA: '27',
  NA: '264',
  BW: '267',
  ZW: '263',
  ZM: '260',
  MW: '265',
  MZ: '258',
  AO: '244',
  CD: '243',
  CG: '242',
  GA: '241',
  GQ: '240',
  CF: '236',
  TD: '235',
  MG: '261',
  MU: '230',
  SC: '248',
  KM: '269',
  LS: '266',
  SZ: '268',
  ST: '239',
  // Europe
  GB: '44',
  IE: '353',
  FR: '33',
  BE: '32',
  NL: '31',
  LU: '352',
  DE: '49',
  AT: '43',
  CH: '41',
  IT: '39',
  ES: '34',
  PT: '351',
  DK: '45',
  NO: '47',
  SE: '46',
  FI: '358',
  IS: '354',
  PL: '48',
  CZ: '420',
  SK: '421',
  HU: '36',
  RO: '40',
  BG: '359',
  GR: '30',
  HR: '385',
  SI: '386',
  RS: '381',
  UA: '380',
  TR: '90',
  RU: '7',
  // Americas
  US: '1',
  CA: '1',
  MX: '52',
  BR: '55',
  AR: '54',
  CL: '56',
  CO: '57',
  PE: '51',
  VE: '58',
  EC: '593',
  BO: '591',
  PY: '595',
  UY: '598',
  CU: '53',
  DO: '1',
  JM: '1',
  TT: '1',
  HT: '509',
  GT: '502',
  HN: '504',
  SV: '503',
  NI: '505',
  CR: '506',
  PA: '507',
  // Asia and the Middle East
  CN: '86',
  JP: '81',
  KR: '82',
  IN: '91',
  PK: '92',
  BD: '880',
  LK: '94',
  NP: '977',
  ID: '62',
  MY: '60',
  SG: '65',
  TH: '66',
  VN: '84',
  PH: '63',
  AE: '971',
  SA: '966',
  QA: '974',
  KW: '965',
  BH: '973',
  OM: '968',
  JO: '962',
  LB: '961',
  IL: '972',
  IR: '98',
  IQ: '964',
  // Oceania
  AU: '61',
  NZ: '64',
  FJ: '679',
  PG: '675',
};

export interface PhoneParts {
  country: string;
  national: string;
}

/** Builds E.164 from a country and what the user typed ("024 123 4567" -> "+233241234567"). */
export function toE164({ country, national }: PhoneParts): string {
  const digits = national.replace(/\D/g, '').replace(/^0+/, '');
  if (digits === '') return '';
  const code = COUNTRY_CALLING_CODES[country] ?? COUNTRY_CALLING_CODES[DEFAULT_COUNTRY];
  return `+${code}${digits}`;
}

/** Splits a stored E.164 number back into a country and the rest (longest code wins). */
export function fromE164(
  value: string | null | undefined,
  preferred = DEFAULT_COUNTRY,
): PhoneParts {
  if (!value?.startsWith('+')) return { country: preferred, national: '' };
  const digits = value.slice(1);
  const preferredCode = COUNTRY_CALLING_CODES[preferred];
  if (preferredCode && digits.startsWith(preferredCode)) {
    return { country: preferred, national: digits.slice(preferredCode.length) };
  }
  let best: PhoneParts | null = null;
  let bestLength = 0;
  for (const [country, code] of Object.entries(COUNTRY_CALLING_CODES)) {
    if (digits.startsWith(code) && code.length > bestLength) {
      best = { country, national: digits.slice(code.length) };
      bestLength = code.length;
    }
  }
  return best ?? { country: preferred, national: digits };
}

/** Countries sorted by their name in `language`, each labelled "Ghana (+233)". */
export function countryOptions(language: string): { value: string; label: string }[] {
  let names: Intl.DisplayNames | null = null;
  try {
    names = new Intl.DisplayNames([language], { type: 'region' });
  } catch {
    names = null;
  }
  return Object.entries(COUNTRY_CALLING_CODES)
    .map(([country, code]) => {
      const name = names?.of(country) ?? country;
      return { value: country, label: `${name} (+${code})`, name };
    })
    .sort((a, b) => a.name.localeCompare(b.name, language))
    .map(({ value, label }) => ({ value, label }));
}
