// عکس از سایتِ زنده از راهِ پلِ حسابداری (هاست ایرانی از خارج بسته است)
// node live-shot.mjs <path> <out.png> [1=mobile] — عکس، خطاهای JS و اسکرولِ افقی؛ رمزِ برنامه از STLAND_WP_USER/PASS
import { chromium } from 'playwright';
const R = 'https://hesabdari.stland.ir/api/site-relay';
const auth = 'Basic ' + Buffer.from(`${process.env.STLAND_WP_USER}:${process.env.STLAND_WP_PASS}`).toString('base64');
const [,, path, out, mobile] = process.argv;
const browser = await chromium.launch();
const ctx = await browser.newContext(mobile ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' } : { viewport: { width: 1366, height: 900 } });
const page = await ctx.newPage();
const errs = [];
page.on('pageerror', e => errs.push('JS: ' + e.message.slice(0, 160) + ' @ ' + (e.stack || '').split('\n').slice(1,3).join(' | ').slice(0, 200)));
page.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 160)); });
await page.route('**/*', async route => {
  const u = new URL(route.request().url());
  if (u.hostname !== 'stland.ir' && u.hostname !== 'www.stland.ir') return route.abort();
  try {
    const r = await fetch(`${R}?path=${encodeURIComponent(u.pathname + u.search)}${mobile ? '&ua=mobile' : ''}`, { headers: { Authorization: auth } });
    const body = Buffer.from(await r.arrayBuffer());
    await route.fulfill({ status: r.status, headers: { 'content-type': r.headers.get('content-type') || 'text/html' }, body });
  } catch (e) { await route.abort(); }
});
await page.goto('https://stland.ir' + path, { waitUntil: 'networkidle', timeout: 180000 }).catch(e => errs.push('goto: ' + e.message.slice(0, 100)));
await page.waitForTimeout(2000);
const info = await page.evaluate(() => {
  const vw = document.documentElement.clientWidth, out = [];
  for (const el of document.querySelectorAll('body *')) { const r = el.getBoundingClientRect(); if (r.width && (r.right > vw + 2 || r.left < -2) && getComputedStyle(el).position !== 'fixed') out.push((el.id ? '#' + el.id : '') + '.' + String(el.className).slice(0, 60) + ' L' + Math.round(r.left) + ' R' + Math.round(r.right)); if (out.length > 12) break; }
  return { vw, sw: document.documentElement.scrollWidth, over: out };
});
console.log(JSON.stringify(info));
await page.screenshot({ path: out, fullPage: false });
console.log(JSON.stringify({ path, errs: [...new Set(errs)].slice(0, 12) }));
await browser.close();
