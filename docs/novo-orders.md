# سفارش‌های سایت در Novo — قراردادِ سمتِ سایت

صاحب فروشگاه (۲۴ آبان ۱۴۰۵): «من وارد سایت نمی‌خوام بشم برای پیگیری یا سفارش. تمام مراحلشو در جای خودش در بخشی که طراحی
کردیم بیار که بتونم تغییرات از هسته‌ی Novo انجام بدم.»

سمتِ سایت از `stland-home` ۱.۸۴.۱۹ آماده است (`includes/orders-feed.php`). همه با رمزِ برنامه‌ی مدیر از دروازه‌ی سایتِ Novo
(همان راهِ `stland/v1/tradein`).

| درخواست | کار |
| --- | --- |
| `GET /wp-json/stland/v1/orders?after=<ISO GMT>&limit=50` | سفارش‌هایی که بعد از آن زمان تغییر کرده‌اند، قدیم به جدید. Novo آخرین `modified_gmt` را نگه دارد و هر دو دقیقه بپرسد. |
| `GET /wp-json/stland/v1/orders?id=<شناسه>` | یک سفارش |
| `GET /wp-json/stland/v1/orders/statuses` | `[{key, label}]` — همه‌ی وضعیت‌ها (با وضعیت‌های افزونه‌ی جایگاه) |
| `POST /wp-json/stland/v1/orders/<id>` با `{status?, tracking_code?, note?}` | تغییر از Novo؛ پاسخ = همان سفارشِ به‌روز |

هر سفارش: `id`، `number`، `status`، `statusLabel`، `created_gmt`، `modified_gmt`، `paid_gmt`، `total`، `currency`، `payment`، `payment_method` (کدِ روش: `WC_Sep_Payment_Gateway`، `WC_ZPal`، `bacs` = واریز به شبا)،
`shipping`، `customer {name, phone, state, city, address, postcode, user_id}`، `note`، `tracking`، `items [{name, qty, total,
product_id, sku}]`، `sms [{type, result, date}]` (۵ پیامکِ آخرِ همان سفارش و جوابِ ملی‌پیامک).

* تغییرِ وضعیت مثلِ پیشخوان است: پیامکِ همان وضعیت (الگوهای ملی‌پیامک) و ایمیل می‌رود و یادداشتِ «از Novo» در سفارش می‌ماند.
* `tracking_code` در متای `post_barcode` می‌نشیند و پیامکِ «کد رهگیری» (الگوی ۲۶۸۳۸۰) را می‌فرستد.
* قیمت، کالا و موجودیِ سفارش از این راه عوض نمی‌شود.

## متن برای نشستِ Novo (صاحب فروشگاه کپی کند)

> سایت از نسخه‌ی ۱.۸۴.۱۹ مسیرِ `stland/v1/orders` دارد (قرارداد در `stland-site/docs/novo-orders.md`). در Novo بخشِ «سفارش‌های سایت»
> بسازید: هر دو دقیقه از دروازه‌ی سایت سفارش‌های تغییرکرده را بخوانید (`after` = آخرین `modified_gmt`)، سفارشِ تازه اعلان بدهد،
> فهرست با وضعیت و مشتری و کالاها و نتیجه‌ی پیامک‌ها، و در هر سفارش: تغییرِ وضعیت از فهرستِ `orders/statuses`، ثبتِ کدِ رهگیریِ پست
> و یادداشت (`POST stland/v1/orders/<id>`). همه از گوشی و راست‌به‌چپ.
