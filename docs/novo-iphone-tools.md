# ابزارهای آیفون — قرارداد با Novo

سه ابزارِ مشتری از پروژه‌ی ربات (`stockland-bot`) به سایت آمده‌اند (`includes/iphone-tools.php`، `assets/ipt/`):

| ابزار | برگه | معادلِ ربات |
|---|---|---|
| کارشناسی قیمت آیفون | `/iphone-price/` | `iphone_valuation` + ویزاردِ مینی‌اپ |
| سلامت آیفون | `/iphone-health/` (شناسنامه‌ی عمومی: `/iphone-health/?code=SL-XXXXX`) | `iphone_health` |
| مقایسه دو آیفون | `/iphone-compare/` | `compare_models` |

**صاحب فروشگاه (۶ آذر ۱۴۰۵): نگه‌داری و تغییرِ همه‌چیز داخلِ Novo است.** سایت موتور و ظاهر را دارد و داده را از Novo می‌گیرد.
تا اولین ارسال، داده‌ی پیش‌فرضِ ربات کار می‌کند (`assets/ipt/data/*.json`: کاتالوگِ ۵۴ مدل، قیمت‌های پایه‌ی ۱۵ آبان فقط برای حافظه‌ی
مرجعِ هر مدل، ضریب‌ها، ۴۲ تستِ فعال، مشخصاتِ ۲۴ مدل).

همه‌ی مسیرها زیرِ `https://stland.ir/wp-json/stland/v1/ipt` و با رمزِ برنامه‌ی مدیر (مثلِ سفارش‌ها).

## ۱. فرستادنِ پیکربندی — `POST /ipt/config`

بدنه JSON است و **هر کلید اختیاری است**؛ هر کلیدی که بیاید جای همان بخش را کامل می‌گیرد، کلیدی که نیاید دست نمی‌خورد، و `null` آن
بخش را به پیش‌فرض برمی‌گرداند. پاسخ: `{ok, updated, keys}`. خواندنِ وضعِ فعلی: `GET /ipt/config` → `{ok, pushed, config}`.

```json
{
  "version": "2026-11-27-1",
  "prices": [
    {"model": "iPhone 17 Pro Max", "storage": "256GB", "base": 650000000, "fx_ref": 0, "demand": 0, "market": 0, "active": true}
  ],
  "unregistered_pct": {"iPhone 17 Pro Max": -46.1538},
  "coefficients": [
    {"category": "battery", "option_key": "batt_85_89", "option_label": "باتری ۸۵ تا ۸۹٪ (افت متوسط)", "percent": -6, "sort_order": 3, "active": 1}
  ],
  "score_weights": {"condition": 20, "battery": 20, "repair": 25, "registry": 15, "box": 10, "cosmetic": 20, "cable": 5, "features": 5, "component": 15, "replaced": 10, "grade": 10},
  "modifiers": [
    {"dim": "color", "scope": "model", "name": "iPhone 17 Pro Max", "key": "نارنجی کیهانی", "pct": 2}
  ],
  "settings": {"buy_pct": 90, "usd_rate": 0, "fx_sensitivity": 0.5, "price": true, "health": true, "compare": true},
  "models": [ {"name": "iPhone 17 Pro Max", "year": "2025", "sort_order": 50, "series_group": "iPhone 17", "storages": ["256GB","512GB","1TB","2TB"], "colors": [{"name": "نارنجی کیهانی", "hex": "#F77E2D"}], "dual_sim_parts": "ZA,CH", "esim_only": 0, "features": ["face_id","dynamic_island","action_button","telephoto","wireless_charge"]} ],
  "parts": [ {"code": "LL/A", "label": "آمریکا"} ],
  "health": {"tests": [ ... ], "categories": [ ... ], "contradiction_rules": [ ... ]},
  "spec_fields": [ {"key": "chip", "label": "تراشه"} ],
  "specs": {"iPhone 17 Pro Max": {"chip": "A19 Pro", "ram": "12GB"}}
}
```

* **`prices`**: یک ردیف برای هر «مدل + حافظه» (نامِ مدل دقیقاً مثلِ `models`). `base` = قیمتِ «دستگاه در حد آک (نه آکبند)، بدون باز
  شدگی، بالاترین باتری، با رجیستر» به تومان. حافظه‌ای که ردیف ندارد در سایت دیده نمی‌شود. `demand` و `market` درصدند (همان «عرضه و
  تقاضای مدل» و «دادهٔ بازار Stland» ربات؛ اگر Novo میانگینِ فروش‌های واقعی را دارد، `market` را خودش حساب کند).
  `fx_ref` نرخِ دلارِ زمانِ ثبتِ `base` است؛ با `settings.usd_rate` و `fx_sensitivity` اثرِ دلار حساب می‌شود (هر دو ۰ = بی اثر).
* **`unregistered_pct`**: درصدِ «بدون رجیستر» برای هر مدل (از جدولِ قیمتِ صاحب فروشگاه: `(بی‌رجیستر ÷ رجیستر − ۱) × ۱۰۰`). مدلِ بی
  عدد → ضریبِ عمومیِ `registry_unregistered` (−۲۰٪).
* **`modifiers`**: درصدِ رنگ/پارت/سری اصالت/رجیستری در سطحِ `model` (با `name` = نامِ مدل)، `series` (با `name` = `series_group`) یا
  `global`؛ اولین برخورد از مدل به سراسری برنده است.
* **`settings.buy_pct`**: «پیشنهاد نقدی Stland» = این درصد از قیمتِ منصفانه (صاحب فروشگاه، ۶ آذر). `price`/`health`/`compare` خاموش
  = ابزار غیرفعال («این قابلیت در حال حاضر غیرفعال است.»).
* **`health`**: هر زیرکلید (`tests`، `categories`، `contradiction_rules`، `verdicts`، `quick_mode_footer`…) جای پیش‌فرض را می‌گیرد؛
  ساختارِ هر تست مثلِ `assets/ipt/data/health_items.json` (`test_key`، `category_key`، `title`، `guide_text_html`، `weight`،
  `is_dealbreaker`، `quick_test`، `requires_feature`، `excludes_feature`، `web_test_key`، `schematic_part`، `active`).
* **`specs`**: با پیش‌فرض ادغام می‌شود (مدلی که بیاید جایگزین می‌شود)؛ مدلی که `chip` دارد در مقایسه می‌آید.

## ۲. خواندنِ داده‌های مشتری

| مسیر | چه می‌دهد |
|---|---|
| `GET /ipt/leads?days=30` | درخواست‌های «🤝 می‌خوام بفروشم»: `phone`، `city`، `model_name`، `capacity_label`، `grade_key`، `estimated_price` (اعلامِ کاربر، تأییدنشده؛ ۰ = توافقی)، `status` (`new`/`contacted`/`archived`)، `admin_note` |
| `POST /ipt/leads/<id>` | `{status, note}` — تغییرِ وضعیت و یادداشت |
| `GET /ipt/valuations?days=30` | کارشناسی‌ها: مدل، حافظه، `fair_price`، `market_price`، `buy_price`، `score`، `verdict`، `city`، `selections` (۳۰۰ تای آخر نگه داشته می‌شود) |
| `GET /ipt/health-reports?days=30` | شناسنامه‌های تکمیل‌شده: `model_name`، `capacity_label`، `mode`، `result` (امتیاز، کامل بودن، حکم، مشکل‌های جدی، تناقض‌ها، خلاصه‌ی فصل‌ها) |

هر درخواستِ فروش یک نامه هم به ایمیلِ مدیرِ سایت می‌فرستد و در پیشخوان («درخواست‌های فروشِ آیفون») دیده می‌شود.

## ۳. قاعده‌ها

* موتورِ قیمت عیناً `pricing_engine` ربات است: همه‌ی اثرها **درصدِ جمع‌شونده** روی `base`؛ گردکردن به نزدیک‌ترین هزار تومان
  (نیمه به زوج، مثلِ پایتون). نمونه‌ی ربات (۱۵ پرو مکس ۲۵۶، پایه ۳۲۰ میلیون، کارکرده، باتری ۸۵–۸۹، خش جزئی، کابل اصلی، بی کارتن)
  در سایت هم ۲۴۳٬۲۰۰٬۰۰۰ و امتیازِ ۷۰ می‌دهد.
* هوشِ مصنوعی در سایت نیست (در ربات هم پیش‌فرض خاموش است).
* درخواستِ فروش ثبت‌نام نمی‌خواهد (سایت حسابِ تلگرامی ندارد)؛ فیلدِ پنهان و سقفِ ۵ در ساعت برای هر آی‌پی. کارشناسی ۱۵ در دقیقه،
  شروعِ تستِ سلامت ۱۵ در ۵ دقیقه.
