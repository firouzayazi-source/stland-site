/**
 * آزمونِ دیداریِ صفحه‌ی اصلی — همان چیزهایی که صاحب فروشگاه با اسکرین‌شات
 * خبر می‌داد، حالا پیش از رسیدن به سایت قرمز می‌شوند:
 *  • روی گوشی و دسکتاپ هیچ اسکرولِ افقی نیست.
 *  • کارت‌های «آیفون نو» و «آیفون کارکرده» هستند — حتی وقتی «تلفن همراه»
 *    دسته‌ی پیش‌فرضِ ووکامرس است (مهر ۱۴۰۵ همین آن‌ها را برد).
 *  • ردیف‌ها به همان ترتیبِ تنظیمات‌اند؛ لوازم جانبی یک کراسول با دکمه‌ی زیردسته‌ها.
 *  • خطِ وضعیت («کارکرده · آبی») زیرِ نامِ گوشی هست؛ هیچ ردیفی خالی نیست.
 *  • خطای جاوااسکریپت نیست.
 */
import { chromium } from 'playwright'
import fs from 'node:fs'

const [file, outDir] = process.argv.slice(2)
const expect = JSON.parse(fs.readFileSync(file + '.expect.json', 'utf8'))
const fail = []
const check = (ok, msg) => { if (!ok) fail.push(msg) }

const titleOf = (l) => (l.mode === 'older' ? `${l.cat} — مدل‌های قدیمی‌تر` : l.cat)
const wantTitles = expect.lines.map(titleOf)

const browser = await chromium.launch()
for (const [name, width, height] of [['mobile', 390, 844], ['desktop', 1280, 900]]) {
  const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 1 })
  const errors = []
  page.on('pageerror', (e) => errors.push(String(e)))
  await page.goto('file://' + file)
  // content-visibility: تا آخر برو تا همه رسم شوند
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 20)) }
    window.scrollTo(0, 0)
  })

  const sw = await page.evaluate(() => document.documentElement.scrollWidth)
  check(sw <= width, `${name}: اسکرولِ افقی (${sw} > ${width})`)

  const cards = await page.$$eval('.st-cat-name', (els) => els.map((e) => e.textContent.trim()))
  for (const c of ['آیفون نو', 'آیفون کارکرده', 'ایرپاد']) check(cards.includes(c), `${name}: کارتِ «${c}» نیست (${cards.join('، ')})`)

  const titles = await page.$$eval('.st-pr-wrapper .st-sec-title h2', (els) => els.map((e) => e.textContent.trim()))
  check(
    JSON.stringify(titles.slice(0, wantTitles.length)) === JSON.stringify(wantTitles),
    `${name}: ترتیبِ ردیف‌ها\n  آمد:  ${titles.join(' | ')}\n  باید: ${wantTitles.join(' | ')}`,
  )

  const empty = await page.$$eval('.st-pr-row', (rows) => rows.filter((r) => !r.querySelector('.st-pr-card')).length)
  check(empty === 0, `${name}: ${empty} ردیفِ خالی`)

  const acc = page.locator('section.st-pr-wrapper', { has: page.locator('h2', { hasText: /^لوازم جانبی$/ }) })
  check((await acc.count()) === 1, `${name}: بخشِ «لوازم جانبی» یکی نیست`)
  check((await acc.locator('.st-pr-row').count()) === 1, `${name}: لوازم جانبی باید یک کراسول باشد`)
  check((await acc.locator('.st-subcat').count()) >= 2, `${name}: دکمه‌ی زیردسته‌ها نیست`)

  const specs = await page.$$eval('.st-pr-spec', (els) => els.map((e) => e.textContent.trim()))
  check(specs.some((s) => s.includes('کارکرده') && s.includes('آبی')), `${name}: خطِ «کارکرده · آبی» زیرِ نام نیست`)

  check(errors.length === 0, `${name}: خطای جاوااسکریپت: ${errors.join(' / ')}`)
  if (outDir) await page.screenshot({ path: `${outDir}/home-${name}.png`, fullPage: true })
  await page.close()
}
await browser.close()

if (fail.length) {
  console.error('✖ آزمونِ دیداری:\n' + fail.map((f) => '  - ' + f).join('\n'))
  process.exit(1)
}
console.log('✔ آزمونِ دیداری: گوشی و دسکتاپ درست‌اند')
