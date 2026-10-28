// Public API URL for a separately hosted backend; no credentials belong here.
const apiBase = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '');
export async function request(path: string, body?: unknown) {
 if (import.meta.env.VITE_DEMO_MODE === 'true') return (await import('./demo')).demoRequest(path, body);
 const response = await fetch(`${apiBase}/${path}`, {
  method: body === undefined ? 'GET' : 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: body === undefined ? undefined : JSON.stringify(body),
 });
 const data = await response.json();
 if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat().join(' ') || data.message || 'No se pudo completar la operación.');
 return data;
}
