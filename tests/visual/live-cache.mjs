// مثلِ live-shot.mjs ولی با کشِ محلی: بارِ اول از پل (آهسته، حداکثر ۲ درخواست)، بارهای بعد بی‌هیچ
// درخواستی به سایت — برای آزمودنِ چند باره‌ی یک CSS در چند عرض بی آنکه به هاست بار بیاید.
// node live-cache.mjs <path> <out.png> <width> [css-file] [1=mobile]
// کش: $LIVE_CACHE یا /tmp/stland-live-cache
import { chromium } from 'playwright';
import { createHash } from 'node:crypto';
import { mkdirSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
const R = 'https://hesabdari.stland.ir/api/site-relay';
const auth = 'Basic ' + Buffer.from(`${process.env.STLAND_WP_USER}:${process.env.STLAND_WP_PASS}`).toString('base64');
const [,, path, out, width = '1366', cssFile, mobile] = process.argv;
const dir = process.env.LIVE_CACHE || '/tmp/stland-live-cache';
mkdirSync(dir, { recursive: true });
const browser = await chromium.launch();
const ctx = await browser.newContext(mobile
  ? { viewport: { width: +width, height: 844 }, deviceScaleFactor: 2, isMobile: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' }
  : { viewport: { width: +width, height: 900 } });
const page = await ctx.newPage();
const errs = [];
page.on('pageerror', e => errs.push('JS: ' + e.message.slice(0, 160)));
let active = 0, fetched = 0;
const slot = async () => { while (active >= 2) await new Promise(r => setTimeout(r, 150)); active++; await new Promise(r => setTimeout(r, 300)); };
await page.route('**/*', async route => {
  const u = new URL(route.request().url());
  if (u.hostname !== 'stland.ir' && u.hostname !== 'www.stland.ir') return route.abort();
  const type = route.request().resourceType();
  if (['font', 'media'].includes(type)) return route.abort();
  const key = createHash('md5').update((mobile ? 'm|' : 'd|') + u.pathname + u.search).digest('hex');
  const f = `${dir}/${key}`;
  if (existsSync(f)) {
    const meta = JSON.parse(readFileSync(f + '.json', 'utf8'));
    return route.fulfill({ status: meta.status, headers: { 'content-type': meta.ct }, body: readFileSync(f) });
  }
  // عکس فقط از کش؛ تازه‌اش را نمی‌گیریم تا بارِ پل کم بماند (جز لوگو و بنرِ سربرگ که در کش نیست = خالی)
  if (type === 'image') return route.abort();
  await slot();
  try {
    const r = await fetch(`${R}?origin=new&path=${encodeURIComponent(u.pathname + u.search)}${mobile ? '&ua=mobile' : ''}`, { headers: { Authorization: auth } });
    const body = Buffer.from(await r.arrayBuffer());
    const ct = r.headers.get('content-type') || 'text/html';
    if (r.status === 200) { writeFileSync(f, body); writeFileSync(f + '.json', JSON.stringify({ status: 200, ct })); }
    fetched++;
    await route.fulfill({ status: r.status, headers: { 'content-type': ct }, body });
  } catch { await route.abort(); } finally { active--; }
});
await page.goto('https://stland.ir' + path, { waitUntil: 'networkidle', timeout: 240000 }).catch(e => errs.push('goto: ' + e.message.slice(0, 100)));
if (cssFile) await page.addStyleTag({ content: readFileSync(cssFile, 'utf8') });
if (process.env.HOVER) { const b = await page.locator(process.env.HOVER).first().boundingBox().catch(() => null); if (b) { await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2); await page.waitForTimeout(800); } else errs.push('hover: not found'); }
await page.waitForTimeout(1500);
const info = await page.evaluate(() => {
  const vw = document.documentElement.clientWidth, over = [];
  for (const el of document.querySelectorAll('body *')) { const r = el.getBoundingClientRect(); if (r.width && r.height && (r.right > vw + 2 || r.left < -2) && getComputedStyle(el).position !== 'fixed') over.push((el.id ? '#' + el.id : '') + '.' + String(el.className).slice(0, 50) + ' L' + Math.round(r.left) + ' R' + Math.round(r.right)); if (over.length > 10) break; }
  return { vw, sw: document.documentElement.scrollWidth, over };
});
if (process.env.INSPECT) {
  const rows = await page.evaluate((sels) => sels.map(sel => { const el = document.querySelector(sel); if (!el) return sel + ' —';
    const r = el.getBoundingClientRect(), c = getComputedStyle(el);
    return `${sel} L${Math.round(r.left)} W${Math.round(r.width)} H${Math.round(r.height)} | w:${c.width} minw:${c.minWidth} maxw:${c.maxWidth} disp:${c.display} flex:${c.flexWrap} pos:${c.position} ov:${c.overflow} h:${c.height} maxh:${c.maxHeight} T${Math.round(r.top)} pad:${c.padding} ml:${c.marginLeft} mr:${c.marginRight}`; }), process.env.INSPECT.split('|'));
  console.log(rows.join('\n'));
}
await page.screenshot({ path: out, clip: { x: 0, y: 0, width: +width, height: 700 } });
console.log(JSON.stringify({ width: +width, fetched, ...info, errs: [...new Set(errs)].slice(0, 6) }));
await browser.close();
