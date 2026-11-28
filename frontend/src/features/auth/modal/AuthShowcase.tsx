import { Check, FileText, Layers, Quote, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import sourcesPhoto from '@/assets/auth/sources.webp';
import { Logo } from '@/components/brand/Logo';

/**
 * The right half of the auth modal on wide screens: the logo, a short pitch and
 * a sample of what Skimr produces (the public hero's "Example synthesis", spec 2:
 * every finding quotes its source). Same layers as the public hero
 * (backend/src/Public/site.css .hero): a washed-out photo, the dot grid and a
 * teal glow, so it adapts to light and dark mode.
 *
 * Decorative for screen readers: the dialog is about logging in, and the pitch
 * repeats the public site. The photo is display:none below lg, and lazy, so
 * phones never download it.
 */
export function AuthShowcase() {
  const { t } = useTranslation('auth');
  const s = (key: string) => t(`modal.showcase.${key}`);

  return (
    <div
      aria-hidden="true"
      className="relative isolate hidden overflow-hidden border-s border-border bg-surface lg:flex lg:flex-col"
    >
      <img
        src={sourcesPhoto}
        alt=""
        loading="lazy"
        decoding="async"
        className="absolute inset-0 -z-30 size-full object-cover opacity-30 grayscale-[0.3] dark:opacity-20"
      />
      <div className="absolute inset-0 -z-20 bg-[linear-gradient(to_bottom,color-mix(in_srgb,var(--surface)_35%,transparent)_0%,color-mix(in_srgb,var(--surface)_72%,transparent)_40%,var(--surface)_78%)]" />
      <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle,var(--scrollbar-thumb)_1.2px,transparent_1.6px)] [mask-image:radial-gradient(ellipse_70%_60%_at_60%_45%,#000_25%,transparent_75%)] bg-size-[22px_22px]" />
      <div className="absolute -end-32 top-1/3 -z-10 size-[30rem] rounded-full bg-[radial-gradient(closest-side,color-mix(in_srgb,var(--primary)_18%,transparent),transparent)]" />

      <div className="flex h-full flex-col px-10 pt-9 pb-10">
        <Logo />

        {/* The sample synthesis card, tilted a touch like a page on a desk. */}
        <div className="my-auto py-8">
          <div className="relative mx-auto w-full max-w-84">
            <div className="absolute inset-x-6 -bottom-3 h-full rounded-2xl border border-border bg-bg/70" />
            <figure className="relative rounded-2xl border border-border bg-bg p-5 shadow-[0_24px_48px_-20px_rgb(0_0_0/0.25)] -rotate-1">
              <figcaption className="flex items-center justify-between gap-3 text-xs text-subtle">
                <span className="font-medium">{s('caption')}</span>
                <span className="flex gap-1">
                  <i className="size-2 rounded-full bg-border" />
                  <i className="size-2 rounded-full bg-border" />
                  <i className="size-2 rounded-full bg-border" />
                </span>
              </figcaption>
              <p className="mt-3 flex items-center gap-1.5 text-xs font-semibold text-fg">
                <Layers className="size-3.5 text-primary" />
                {s('theme')}
              </p>

              <div className="mt-3 rounded-xl border border-border bg-surface p-3.5">
                <p className="flex items-center gap-1.5 text-[0.6875rem] font-semibold tracking-wide text-muted uppercase">
                  <Quote className="size-3" />
                  {s('finding')}
                </p>
                <blockquote className="mt-1.5 border-s-2 border-primary ps-2.5 text-[0.8125rem] leading-snug text-fg">
                  {s('quote')}
                </blockquote>
                <span className="mt-2.5 inline-flex items-center gap-1 rounded-full border border-border bg-bg px-2 py-0.5 text-[0.6875rem] text-muted">
                  <FileText className="size-3" />
                  {s('source')}
                </span>
              </div>

              <div className="mt-2.5 rounded-xl bg-primary-subtle p-3.5">
                <p className="flex items-center gap-1.5 text-[0.6875rem] font-semibold tracking-wide text-primary uppercase">
                  <Sparkles className="size-3" />
                  {s('interpretation')}
                </p>
                <p className="mt-1.5 text-[0.8125rem] leading-snug text-fg">
                  {s('interpretation_text')}
                </p>
                <p className="mt-2 flex items-center gap-1 text-[0.6875rem] font-medium text-primary">
                  <Check className="size-3" />
                  {s('support')}
                </p>
              </div>
            </figure>
          </div>
        </div>

        <div>
          <p className="text-xs font-semibold tracking-[0.08em] text-primary uppercase">
            {s('kicker')}
          </p>
          <p className="mt-2 text-2xl leading-tight font-semibold tracking-[-0.02em] text-balance text-fg">
            {s('headline')}
          </p>
          <p className="mt-2.5 text-sm leading-relaxed text-pretty text-muted">{s('body')}</p>
        </div>
      </div>
    </div>
  );
}
