/**
 * GET request via WordPress AJAX or REST API.
 *
 * @param {string} action   - AJAX action name OR REST path (e.g. "products").
 * @param {object} params   - Query parameters.
 * @param {object} options  - { useRest: false }
 * @returns {Promise<any>}
 */
export default async function fetchData(action, params = {}, options = {}) {
  const { useRest = false } = options;
  const settings = window.ekuseyEcom || {};

  if (useRest) {
    const url = new URL(settings.rest_url + action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

    const res = await fetch(url.toString(), {
      headers: {
        'X-WP-Nonce': settings.rest_nonce,
      },
    });

    if (!res.ok) throw new Error(`REST error ${res.status}`);
    return res.json();
  }

  // AJAX fallback.
  const url = new URL(settings.ajax_url);
  url.searchParams.set('action', action);
  url.searchParams.set('nonce', settings.nonce);
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

  const res = await fetch(url.toString());
  if (!res.ok) throw new Error(`AJAX error ${res.status}`);
  return res.json();
}
