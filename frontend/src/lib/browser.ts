/**
 * Full page loads (leaving the React app), in one place so tests can mock it:
 * jsdom can't spy on window.location.assign.
 */
export function goTo(url: string): void {
  window.location.assign(url);
}
