/**
 * آزمونِ دیداریِ صفحه‌ی «لیست آک و کارکرده» (stland.ir/list):
 *  • روی گوشی و دسکتاپ اسکرولِ افقی نیست؛ دو بخشِ آک و کارکرده با زبانه‌ها.
 *  • گوشیِ فروخته‌شده با اینکه در فهرستِ حسابداری است، دیده نمی‌شود (موجودی زنده از ووکامرس).
 *  • بخشِ ناشناخته‌ی فهرست دور ریخته می‌شود؛ چیپ‌ها و قیمت روی کارت‌اند.
 */
import { chromium } from 'playwright'
import fs from 'node:fs'

const [file, outDir] = process.argv.slice(2)
const expect = JSON.parse(fs.readFileSync(file + '.expect.json', 'utf8'))
const fail = []
const check = (ok, msg) => { if (!ok) fail.push(msg) }
check(expect.received?.ok === true, `دریافتِ فهرست: ${JSON.stringify(expect.received)}`)

const browser = await chromium.launch()
for (const [name, width, height] of [['mobile', 390, 844], ['desktop', 1280, 900]]) {
  const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 1 })
  const errors = []
  page.on('pageerror', (e) => errors.push(String(e)))
  await page.goto('file://' + file)
  const sw = await page.evaluate(() => document.documentElement.scrollWidth)
  check(sw <= width, `list ${name}: اسکرولِ افقی (${sw} > ${width})`)
  const heads = await page.$$eval('.stlh-al-sec h2', (els) => els.map((e) => e.firstChild.textContent.trim()))
  check(JSON.stringify(heads) === JSON.stringify(['آک', 'کارکرده']), `list ${name}: بخش‌ها ${heads.join(' | ')}`)
  check((await page.locator('.stlh-al-tab').count()) === 2, `list ${name}: زبانه‌ها`)
  const names = await page.$$eval('.stlh-al-name', (els) => els.map((e) => e.textContent))
  check(names.length >= 8, `list ${name}: کارت‌ها کم‌اند (${names.length})`)
  check(!names.some((n) => n.includes('فروخته‌شده')), `list ${name}: گوشیِ فروخته‌شده آمد`)
  check((await page.locator('.stlh-al-chip').count()) > 0, `list ${name}: چیپ نیست`)
  check((await page.locator('.stlh-al-price del').count()) >= 1, `list ${name}: قیمتِ پیش از حراج خط‌خورده نیست`)
  check(!(await page.content()).includes('<b>x</b>'), `list ${name}: بخشِ ناشناخته آمد`)
  check(errors.length === 0, `list ${name}: خطای جاوااسکریپت: ${errors.join(' / ')}`)
  if (outDir) await page.screenshot({ path: `${outDir}/list-${name}.png`, fullPage: true })
  await page.close()
}
await browser.close()

if (fail.length) {
  console.error('✖ آزمونِ دیداریِ لیست:\n' + fail.map((f) => '  - ' + f).join('\n'))
  process.exit(1)
}
console.log('✔ آزمونِ دیداریِ لیست: گوشی و دسکتاپ درست‌اند')
