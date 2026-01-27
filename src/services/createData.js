/**
 * POST request via WordPress AJAX or REST API.
 *
 * @param {string} action   - AJAX action name OR REST path.
 * @param {object} body     - Request body (JSON).
 * @param {object} options  - { useRest: false }
 * @returns {Promise<any>}
 */
export default async function createData(action, body = {}, options = {}) {
  const { useRest = false } = options;
  const settings = window.ekuseyEcom || {};

  if (useRest) {
    const res = await fetch(settings.rest_url + action, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': settings.rest_nonce,
      },
      body: JSON.stringify(body),
    });

    if (!res.ok) throw new Error(`REST error ${res.status}`);
    return res.json();
  }

  // AJAX fallback.
  const formData = new FormData();
  formData.append('action', action);
  formData.append('nonce', settings.nonce);
  Object.entries(body).forEach(([k, v]) => formData.append(k, v));

  const res = await fetch(settings.ajax_url, {
    method: 'POST',
    body: formData,
  });

  if (!res.ok) throw new Error(`AJAX error ${res.status}`);
  return res.json();
}
