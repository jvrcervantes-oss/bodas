// Worker de Cloudflare para las webs de boda: <slug>.bodaenlace.com (26-sep-2026, rev. previa #116).
//
// Hostinger compartido no sirve subdominios comodín, así que el Worker recibe <slug>.bodaenlace.com,
// lo reenvía a bodaenlace.com y firma de qué boda se trata. El origen (app/proxy.php) comprueba la
// firma; sin ella, la cabecera no vale nada. Reglas de Seguridad y Deploy #116:
//  · El slug sale del hostname, nunca de una cabecera. Las X-Boda-* del cliente se borran.
//  · Firma HMAC-SHA256(secreto, "slug|ts|ip") con ventana de 60 s. El secreto es un secret del Worker
//    (BODA_PROXY_SECRETO), nunca va en el repo ni en wrangler.toml.
//  · redirect: 'manual'. Solo se reescribe un Location cuyo host sea exactamente bodaenlace.com.
//  · Sin caché compartida salvo /assets/: la clave de caché no lleva el slug y mezclaría bodas.
//  · El cuerpo pasa en stream (fotos de hasta 4 MB) y no se lee en memoria.
const BASE = 'bodaenlace.com';
const SLUG_RE = /^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/;

async function firma(secreto, texto) {
  const k = await crypto.subtle.importKey('raw', new TextEncoder().encode(secreto), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
  const s = await crypto.subtle.sign('HMAC', k, new TextEncoder().encode(texto));
  return [...new Uint8Array(s)].map(b => b.toString(16).padStart(2, '0')).join('');
}

function noEncontrada() {
  return new Response('Esta página no existe', { status: 404, headers: { 'content-type': 'text/plain; charset=utf-8' } });
}

export default {
  async fetch(req, env) {
    const url = new URL(req.url);
    const host = url.hostname.toLowerCase();
    if (host === 'www.' + BASE) return Response.redirect('https://' + BASE + url.pathname + url.search, 301);
    if (!host.endsWith('.' + BASE)) return noEncontrada();
    const slug = host.slice(0, -(BASE.length + 1));
    if (!SLUG_RE.test(slug) || slug.includes('--')) return noEncontrada();
    const secreto = env.BODA_PROXY_SECRETO || '';
    if (secreto.length < 32) return new Response('Servicio no disponible', { status: 503 });

    const ip = req.headers.get('cf-connecting-ip') || '';
    const ts = String(Math.floor(Date.now() / 1000));
    const cab = new Headers(req.headers);
    for (const k of [...cab.keys()]) if (k.startsWith('x-boda-')) cab.delete(k);
    cab.delete('host');
    cab.set('x-boda-slug', slug);
    cab.set('x-boda-ts', ts);
    cab.set('x-boda-ip', ip);
    cab.set('x-boda-firma', await firma(secreto, slug + '|' + ts + '|' + ip));

    const esAsset = url.pathname.startsWith('/assets/');
    const init = { method: req.method, headers: cab, redirect: 'manual' };
    if (!['GET', 'HEAD'].includes(req.method)) init.body = req.body;
    if (!esAsset) init.cache = 'no-store';
    const resp = await fetch('https://' + BASE + url.pathname + url.search, init);

    const salida = new Response(resp.body, resp);
    const loc = resp.headers.get('location');
    if (loc) {
      try {
        const l = new URL(loc, 'https://' + BASE);
        if (l.hostname === BASE) salida.headers.set('location', 'https://' + host + l.pathname + l.search + l.hash);
      } catch (e) { /* Location ilegible: se deja como vino */ }
    }
    return salida;
  },
};
