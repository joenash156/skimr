/**
 * What sits behind the auth modal on the /app routes and behind the signed-out
 * cards (verify, reset, invite): the page colour with the public site's dot
 * grid (backend/src/Public/site.css .hero__dotgrid), fading out from the centre.
 */
export function AuthBackdrop() {
  return (
    <div aria-hidden="true" className="fixed inset-0 -z-10 bg-bg">
      <div className="absolute inset-0 bg-[radial-gradient(circle,var(--scrollbar-thumb)_1.2px,transparent_1.6px)] [mask-image:radial-gradient(ellipse_at_center,#000_30%,transparent_75%)] bg-size-[22px_22px]" />
    </div>
  );
}
