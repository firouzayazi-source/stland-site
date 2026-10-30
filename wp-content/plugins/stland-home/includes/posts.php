<?php
/**
 * مقاله‌های وبلاگ از مخزن.
 *
 * صاحب فروشگاه (۲۳ مهر ۱۴۰۵): «من مطلب بدم با عکس، خودت بذاری». پل فقط‌خواندنی است، پس مقاله مثلِ
 * تنظیماتِ قالب با نسخه‌ی افزونه می‌رود: متن در `content/posts/<نامک>.html` (بلوک‌های گوتنبرگ)، عکس‌ها در
 * `assets/posts/`. هر مقاله یک بار ساخته می‌شود و هر بار که تعریف یا متنش عوض شد به‌روز می‌شود.
 *
 * • عکس‌ها یک بار به کتابخانه‌ی رسانه می‌روند (با اندازه‌های وردپرس)؛ شناسه‌شان در `stlh_post_media`.
 * • `{{img:کلید}}` → بلوکِ عکس، `{{cat:نامِ دسته‌ی محصول}}` → نشانیِ آن دسته، `{{post:ID}}` → نشانیِ آن مقاله،
 *   `{{slug:نامک}}` → مقاله‌ی منتشرشده با آن نامک (مقاله‌ی تازه‌ی همین فایل؛ تعریفش را بالاتر از لینک‌دهنده بگذارید).
 * • `post_id` = بازنویسیِ مقاله‌ای که از قبل در سایت هست (`keep_slug` نشانی‌اش را نگه می‌دارد).
 * • وضعیتِ اول از `status` است (`draft` تا صاحب فروشگاه ببیند)؛ بعد از آن وضعیت دست نمی‌خورد.
 * ⛔ اگر صاحب فروشگاه مقاله را در پیشخوان ویرایش کرده باشد، روی ویرایشش نوشته نمی‌شود.
 */
defined( 'ABSPATH' ) || exit;

const STLH_POSTS = [
	// برنامه‌ی محتوا، روزِ ۱۱ (۸ آبان ۱۴۰۵). عکس‌ها از صاحب فروشگاه، همه سری ۱۷ (سه عکسِ اولش سری ۱۶ بود و به خواستِ خودش عوض
	// شد): ۱۷ پرو مکسِ نارنجی و ۱۷ پروِ سرمه‌ای کنارِ هم (عکسِ اصلی)، هر دو در دست (نشانِ مجله‌ی منبع بریده شد)، و برگه‌ی مشخصات
	// (خطِ قیمتِ دلاری بریده شد). جدول و «کدام برای شما» از ما. مشخصات از اعلامِ اپل؛ بی قیمت. «منتشر کن» همان روز (عکسِ دیگری نبود).
	'pro-vs-pro-max' => [
		'title'    => 'پرو یا پرو مکس؟ فرق آیفون پرو و پرو مکس در اندازه، باتری و دوربین؛ کدام را بخریم',
		'status'   => 'publish',
		'excerpt'  => 'پرو یا پرو مکس؟ در آیفون ۱۷ و ۱۶ دوربین و پردازنده یکی است و فرق در اندازه‌ی صفحه، وزن و باتری است. پرو برای کار یک‌دستی و جیب، پرو مکس برای باتری بیشتر و صفحه بزرگ.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'پرو یا پرو مکس', 'آیفون ۱۷ پرو مکس', 'آیفون ۱۷ پرو', 'فرق پرو و پرو مکس', 'خرید آیفون' ],
		'seo'      => [
			'title' => 'پرو یا پرو مکس؟ فرق اندازه، باتری و دوربین آیفون | استوک لند',
			'desc'  => 'آیفون پرو یا پرو مکس؟ مقایسه اندازه صفحه، وزن، باتری، دوربین و حافظه در آیفون ۱۷، ۱۶ و ۱۵ پرو و اینکه کدام برای شما مناسب‌تر است.',
			'kw'    => 'پرو یا پرو مکس',
		],
		'thumb'    => [ 'file' => 'pro-vs-pro-max-hero.webp', 'alt' => 'آیفون ۱۷ پرو مکس نارنجی و آیفون ۱۷ پرو سرمه‌ای کنار هم؛ فرق اندازه' ],
		'images'   => [
			'hand'    => [ 'file' => 'pro-vs-pro-max-hand.webp', 'alt' => 'آیفون ۱۷ پرو سرمه‌ای جلوی آیفون ۱۷ پرو مکس سفید در دست' ],
			'specs'   => [ 'file' => 'pro-vs-pro-max-specs.webp', 'alt' => 'مشخصات آیفون ۱۷ پرو و ۱۷ پرو مکس کنار هم' ],
			'compare' => [ 'file' => 'pro-vs-pro-max-compare.webp', 'alt' => 'جدول مقایسه آیفون ۱۷ پرو و ۱۷ پرو مکس؛ صفحه، وزن، باتری و حافظه' ],
			'pick'    => [ 'file' => 'pro-vs-pro-max-pick.webp', 'alt' => 'آیفون پرو یا پرو مکس برای چه کسی مناسب است' ],
		],
	],
	// خبر (۶ آبان ۱۴۰۵، بیرون از برنامه‌ی ۳۰ روزه): متن و دو عکس از صاحب فروشگاه («یه مقاله میخوام برای اخبار جدید»). عددها با
	// گزارش‌های خبری سنجیده شد (۲۷ اکتبر، ۱٬۴۹۹/۱٬۶۹۹ دلار، Wi-Fi 7، تا چهار Thunderbolt 4، هفت رنگ)؛ نامِ «Siri AI» تأیید نشد و
	// «نسخه‌ی جدید Siri» آمد. قیمت دلاریِ رسمیِ اپل است، نه قیمتِ فروشگاه (مثلِ خبرِ آیفون Duo).
	'apple-imac-m6-2026' => [
		'title'    => 'معرفی iMac جدید اپل با تراشه M6؛ قدرت بیشتر و قابلیت‌های هوش مصنوعی پیشرفته‌تر',
		'status'   => 'publish',
		'excerpt'  => 'اپل از iMac جدید با تراشه M6 رونمایی کرد: تا ۵ برابر هوش مصنوعی سریع‌تر از M4، Wi-Fi 7، تا چهار درگاه Thunderbolt 4، هفت رنگ و قیمت پایه ۱٬۴۹۹ دلار؛ پیش‌فروش از ۲۸ اکتبر و عرضه ۶ نوامبر ۲۰۲۶.',
		'category' => [ 'name' => 'اخبار اپل', 'slug' => 'apple-news' ],
		'tags'     => [ 'آی‌مک جدید اپل', 'iMac M6', 'قیمت iMac جدید', 'مشخصات iMac 2026', 'کامپیوتر اپل', 'تراشه M6' ],
		'seo'      => [
			'title' => 'معرفی iMac جدید اپل با تراشه M6؛ مشخصات، قیمت و زمان عرضه',
			'desc'  => 'اپل از iMac جدید با تراشه M6 رونمایی کرد. بررسی مشخصات فنی، نمایشگر ۲۴ اینچی، قابلیت‌های هوش مصنوعی، رنگ‌بندی، قیمت و زمان عرضه iMac 2026.',
			'kw'    => 'آی‌مک جدید اپل',
		],
		'thumb'    => [ 'file' => 'apple-imac-m6-hero.webp', 'alt' => 'iMac جدید اپل با تراشه M6 در هفت رنگ' ],
		'images'   => [
			'chip' => [ 'file' => 'apple-imac-m6-chip.webp', 'alt' => 'تراشه Apple M6 و iMac بنفش' ],
		],
	],
	'iphone-duo-repair-cost' => [
		'title'    => 'هزینه تعمیر آیفون Duo بدون AppleCare+؛ از ۴۹ تا ۱۴۹۹ دلار!',
		'status'   => 'draft',
		'excerpt'  => 'اپل هزینه‌های تعمیر آیفون Duo را برای کاربرانِ بدون AppleCare+ اعلام کرد؛ از ۴۹ دلار برای لایه محافظ تا ۹۹۹ دلار برای نمایشگر داخلی و ۱۴۹۹ دلار برای سایر آسیب‌ها.',
		'category' => [ 'name' => 'اخبار اپل', 'slug' => 'apple-news' ],
		'tags'     => [ 'آیفون Duo', 'AppleCare+', 'تعمیرات آیفون', 'گوشی تاشو اپل', 'هزینه تعمیر آیفون', 'محصولات اپل' ],
		'seo'      => [
			'title' => 'هزینه تعمیر آیفون Duo بدون AppleCare+؛ جدول کامل قیمت‌ها',
			'desc'  => 'هزینه تعمیر آیفون Duo بدون AppleCare+ تا ۱۴۹۹ دلار می‌رسد. جدول کامل قیمت تعمیر نمایشگر، باتری، شیشه پشتی و مقایسه هزینه‌ها با AppleCare+ را بخوانید.',
			'kw'    => 'هزینه تعمیر آیفون Duo',
		],
		'thumb'    => [ 'file' => 'iphone-duo-hero.webp', 'alt' => 'هزینه تعمیر آیفون Duo با و بدون AppleCare+' ],
		'images'   => [
			'colors' => [ 'file' => 'iphone-duo-colors.webp', 'alt' => 'آیفون Duo در دو رنگ سفید و سرمه‌ای' ],
			'fold'   => [ 'file' => 'iphone-duo-fold.webp', 'alt' => 'نمایشگر داخلی آیفون Duo در حالت باز' ],
		],
	],
	// برنامه‌ی محتوا، روزِ ۱۰ (۷ آبان ۱۴۰۵؛ تاریخ‌ها از همین روز جلو رفت). سه عکس از صاحب فروشگاه: خودش با آیفون ۱۷ پرو و ۱۶ پرو
	// (عکسِ اصلی؛ «عکسِ خودمه»)، خانواده‌ی آیفون ۱۷، و ۱۷ پروِ سفید و نارنجی — برای همین یک بندِ کوتاهِ «و مدل‌های پرو؟» آمد. جدول و
	// «کدام برای شما» از ما. مشخصات از اعلامِ اپل؛ بی قیمت. بالاتر از «آیفون ۱۵ یا ۱۶» تا لینکِ `{{slug:iphone-17-vs-16}}`ِ آن پیدا شود.
	'iphone-17-vs-16' => [
		'title'    => 'آیفون ۱۷ چه فرقی با ۱۶ دارد؟ صفحه ۱۲۰ هرتز، دوربین‌ها و حافظه؛ کدام را بخریم',
		'status'   => 'publish',
		'excerpt'  => 'تفاوت آیفون ۱۷ و ۱۶: صفحه ۱۲۰ هرتز و همیشه‌روشن، دوربین واید ۴۸ و جلوی ۱۸ مگاپیکسلی، پردازنده A19 و حافظه پایه ۲۵۶ گیگ. آیفون ۱۶ برای بودجه کمتر، ۱۷ برای چند سال آینده.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'تفاوت آیفون ۱۷ و ۱۶', 'آیفون ۱۷', 'آیفون ۱۶', 'آیفون ۱۷ پرو', 'خرید آیفون' ],
		'seo'      => [
			'title' => 'تفاوت آیفون ۱۷ و ۱۶؛ صفحه ۱۲۰ هرتز، دوربین و حافظه | استوک لند',
			'desc'  => 'تفاوت آیفون ۱۷ و ۱۶: صفحه ۱۲۰ هرتز، دوربین واید ۴۸ و جلوی ۱۸ مگاپیکسلی، A19، حافظه پایه ۲۵۶ گیگ و شارژ سریع‌تر. کدام برای شما بهتر است؟',
			'kw'    => 'تفاوت آیفون ۱۷ و ۱۶',
		],
		'thumb'    => [ 'file' => 'iphone-17-vs-16-hero.webp', 'alt' => 'آیفون ۱۷ پرو آبی و آیفون ۱۶ پرو مشکی در دستِ مدیرِ استوک لند' ],
		'images'   => [
			'family'  => [ 'file' => 'iphone-17-vs-16-family.webp', 'alt' => 'خانواده آیفون ۱۷؛ آیفون ۱۷ سبز در کنار ۱۷ پرو و ۱۷ ایر' ],
			'compare' => [ 'file' => 'iphone-17-vs-16-compare.webp', 'alt' => 'جدول مقایسه آیفون ۱۶ و ۱۷؛ صفحه، پردازنده، دوربین و حافظه' ],
			'pro'     => [ 'file' => 'iphone-17-vs-16-pro.webp', 'alt' => 'آیفون ۱۷ پرو سفید و نارنجی با نوار دوربین تازه' ],
			'pick'    => [ 'file' => 'iphone-17-vs-16-pick.webp', 'alt' => 'آیفون ۱۶ یا ۱۷ برای چه کسی مناسب است' ],
		],
	],
	// برنامه‌ی محتوا، روزِ ۹ (۶ آبان ۱۴۰۵، روزِ خودش ۳ آبان بود). عکس‌ها از صاحب فروشگاه (همان روز): دو آیفون ۱۳ سفید و سرمه‌ای
	// روی ویترین (عکسِ اصلی) و ۱۳ سفید در دست؛ نقطه‌های بازتابِ چراغ و لکه‌ی نور را به خواستِ خودش برداشتیم. سه عکسِ جلوی
	// گوشی بازتابِ نارنجی روی ناچ داشتند و عکسِ خودش تابلوی مغازه‌های دیگرِ بازار را — کنار ماند. جدول و چک‌لیست از ما. بالاتر از
	// «کدام آیفون» تا لینکِ `{{slug:iphone-13-worth-it}}`ِ آن در همان اجرا پیدا شود.
	'iphone-13-worth-it' => [
		'title'    => 'آیفون ۱۳ هنوز ارزش خرید دارد؟ نقاط قوت، کمبودها و نکات خرید کارکرده',
		'status'   => 'publish',
		'excerpt'  => 'آیفون ۱۳ هنوز ارزش خرید دارد؟ برای بودجه محدود و کارهای روزمره بله؛ برای استفاده چند ساله و بازی سنگین، نسل‌های جدیدتر. مقایسه با ۱۴ و ۱۵ و چک‌لیست خرید آیفون ۱۳ کارکرده.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'خرید آیفون ۱۳', 'آیفون ۱۳', 'آیفون ۱۳ کارکرده', 'آیفون اقتصادی', 'آیفون کارکرده قم' ],
		'seo'      => [
			'title' => 'خرید آیفون ۱۳ هنوز ارزش دارد؟ مقایسه با ۱۴ و ۱۵ | استوک لند',
			'desc'  => 'خرید آیفون ۱۳ هنوز به‌صرفه است؟ نقاط قوت و کمبودها، مقایسه با آیفون ۱۴ و ۱۵، برای چه کسی مناسب است و چک‌لیست خرید آیفون ۱۳ کارکرده.',
			'kw'    => 'خرید آیفون ۱۳',
		],
		'thumb'    => [ 'file' => 'iphone-13-worth-it-hero.webp', 'alt' => 'دو آیفون ۱۳ سفید و سرمه‌ای روی ویترین استوک لند' ],
		'images'   => [
			'store'   => [ 'file' => 'iphone-13-worth-it-store.webp', 'alt' => 'آیفون ۱۳ سفید در دست در فروشگاه استوک لند قم' ],
			'compare' => [ 'file' => 'iphone-13-worth-it-compare.webp', 'alt' => 'جدول مقایسه آیفون ۱۳ با آیفون ۱۴ و ۱۵' ],
			'check'   => [ 'file' => 'iphone-13-worth-it-check.webp', 'alt' => 'چک‌لیست خرید آیفون ۱۳ کارکرده؛ باتری، قطعات، فیس‌آیدی و رجیستری' ],
		],
	],
	// برنامه‌ی محتوا، روزِ ۸ (۵ آبان ۱۴۰۵، روزِ خودش ۲ آبان بود؛ روزِ ۷ بازبینی است و منتظرِ دیدنِ صفحه‌ی دسته‌ها). عکسِ اصلی از
	// دو عکسِ اپل که صاحب فروشگاه فرستاد (آیفون ۱۵ سبز و ۱۶ صورتی، پشت و پهلو)؛ دو عکسِ ۱۶ پرو برای روزِ ۱۱ کنار ماند. جدول و
	// «کدام برای شما» را خودمان ساختیم. مشخصات از اعلامِ اپل؛ هیچ قیمتی نیامده. بالاتر از «کدام آیفون» تا لینکِ
	// `{{slug:iphone-15-vs-16}}`ِ آن در همان اجرا پیدا شود.
	'iphone-15-vs-16' => [
		'title'    => 'آیفون ۱۵ یا ۱۶؟ فرق‌های واقعی، جدول مقایسه و این‌که کدام را بخرید',
		'status'   => 'publish',
		'excerpt'  => 'آیفون ۱۵ یا ۱۶؟ ظاهر، صفحه و دوربین اصلی تقریباً یکی است؛ آیفون ۱۶ پردازنده A18 با ۸ گیگ رم، دکمه اکشن و کنترل دوربین، عکس ماکرو و هوش مصنوعی اپل دارد. برای استفاده طولانی ۱۶، برای بودجه محدود ۱۵.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'مقایسه آیفون ۱۵ و ۱۶', 'آیفون ۱۶', 'آیفون ۱۵', 'خرید آیفون', 'آیفون کارکرده قم' ],
		'seo'      => [
			'title' => 'آیفون ۱۵ یا ۱۶؟ مقایسه آیفون ۱۵ و ۱۶ و کدام را بخریم | استوک لند',
			'desc'  => 'مقایسه آیفون ۱۵ و ۱۶: پردازنده و رم، دوربین واید و ماکرو، دکمه اکشن و کنترل دوربین، هوش مصنوعی اپل و باتری. کدام برای شما بهتر است؟',
			'kw'    => 'مقایسه آیفون ۱۵ و ۱۶',
		],
		'thumb'    => [ 'file' => 'iphone-15-vs-16-hero.webp', 'alt' => 'آیفون ۱۵ سبز و آیفون ۱۶ صورتی از پشت و پهلو' ],
		'images'   => [
			'compare' => [ 'file' => 'iphone-15-vs-16-compare.webp', 'alt' => 'جدول مقایسه آیفون ۱۵ و ۱۶؛ پردازنده، دوربین واید، دکمه‌ها، هوش مصنوعی و شارژ' ],
			'pick'    => [ 'file' => 'iphone-15-vs-16-pick.webp', 'alt' => 'آیفون ۱۵ یا ۱۶ برای چه کسی مناسب است' ],
		],
	],
	// مقاله‌ی موجود (۹ شهریور، از پیشخوان): بازنویسی برای سئو، عکسِ شاخص و دو طرحِ داخلِ متن (صاحب فروشگاه، ۲۳ مهر)
	'which-iphone-to-buy'    => [
		'post_id'   => 44340,
		'keep_slug' => true, // نشانیِ منتشرشده عوض نشود
		'title'     => 'کدام آیفون ارزش خرید بیشتری دارد؟ مقایسه مدل‌های مختلف آیفون برای خرید',
		'status'    => 'publish',
		'excerpt'   => 'کدام آیفون بخریم؟ مقایسه ارزش خرید آیفون معمولی، Plus، Pro، Pro Max و آیفون کارکرده بر اساس نیاز و بودجه، با جدول مقایسه و چک‌لیست خرید آیفون کارکرده.',
		'category'  => [ 'id' => 2968 ],
		'tags'      => [ 'کدام آیفون بخریم', 'مقایسه آیفون', 'آیفون کارکرده', 'راهنمای خرید آیفون', 'آیفون Pro' ],
		'seo'       => [
			'title' => 'کدام آیفون بخریم؟ مقایسه ارزش خرید مدل‌های آیفون | استوک لند',
			'desc'  => 'کدام آیفون بخریم؟ مقایسه ارزش خرید آیفون معمولی، Pro، Pro Max و کارکرده بر اساس نیاز و بودجه، با جدول مقایسه و چک‌لیست خرید آیفون کارکرده.',
			'kw'    => 'کدام آیفون بخریم',
		],
		'thumb'     => [ 'file' => 'which-iphone-hero.webp', 'alt' => 'کدام آیفون ارزش خرید بیشتری دارد؟ مقایسه مدل‌های آیفون' ],
		'images'    => [
			'guide'     => [ 'file' => 'which-iphone-guide.webp', 'alt' => 'کدام آیفون بخریم؛ مقایسه آیفون معمولی، Pro، Pro Max و Pro کارکرده' ],
			'checklist' => [ 'file' => 'which-iphone-checklist.webp', 'alt' => 'چک‌لیست خرید آیفون کارکرده در ۸ مرحله' ],
		],
	],
	// دو مقاله‌ی موجودِ دیگر (۹ شهریور): عکسِ نامناسب → طرحِ اختصاصی از عکس‌های صاحب فروشگاه + مرتب‌سازی برای سئو (۲۴ مهر)
	'iphone-buying-guide'    => [
		'post_id'   => 44342,
		'keep_slug' => true,
		'title'     => 'راهنمای خرید آیفون؛ قبل از خرید گوشی اپل به این نکات توجه کنید',
		'status'    => 'publish',
		'excerpt'   => 'راهنمای خرید آیفون نو و کارکرده در ۱۰ نکته: نیاز و ظرفیت، رجیستری، IMEI، سلامت باتری، نمایشگر، Face ID و سابقه قطعات، قبل از پرداخت.',
		'category'  => [ 'id' => 2968 ],
		'tags'      => [ 'راهنمای خرید آیفون', 'خرید آیفون', 'آیفون کارکرده', 'رجیستری آیفون', 'سلامت باتری آیفون' ],
		'seo'       => [
			'title' => 'راهنمای خرید آیفون نو و کارکرده؛ ۱۰ نکته قبل از خرید | استوک لند',
			'desc'  => 'راهنمای خرید آیفون در ۱۰ نکته: انتخاب مدل و ظرفیت، نو یا کارکرده، رجیستری و IMEI، سلامت باتری، نمایشگر، Face ID و سابقه قطعات قبل از پرداخت.',
			'kw'    => 'راهنمای خرید آیفون',
		],
		'thumb'     => [ 'file' => 'iphone-buying-guide-hero.webp', 'alt' => 'راهنمای خرید آیفون؛ نکات قبل از خرید گوشی اپل' ],
		'images'    => [
			'tips' => [ 'file' => 'iphone-buying-guide-tips.webp', 'alt' => '۱۰ نکته خرید آیفون نو و کارکرده' ],
		],
	],
	'original-iphone'        => [
		'post_id'   => 44341,
		'keep_slug' => true,
		'title'     => 'آیفون اصل را چگونه تشخیص دهیم؟ بررسی رجیستری، اکتیو بودن و اصالت گوشی',
		'status'    => 'publish',
		'excerpt'   => 'آیفون اصل را چگونه تشخیص دهیم؟ بررسی IMEI و شماره سریال، رجیستری، تفاوت اکتیو و Activation Lock، صفحه Hello و سابقه قطعات، با چک‌لیست کامل قبل از خرید.',
		'category'  => [ 'id' => 2968 ],
		'tags'      => [ 'تشخیص آیفون اصل', 'رجیستری آیفون', 'Activation Lock', 'IMEI آیفون', 'آیفون کارکرده' ],
		'seo'       => [
			'title' => 'تشخیص آیفون اصل؛ رجیستری، اکتیو و Activation Lock | استوک لند',
			'desc'  => 'تشخیص آیفون اصل قبل از خرید: بررسی IMEI و سریال، رجیستری، تفاوت اکتیو با Activation Lock، صفحه Hello و سابقه قطعات، با چک‌لیست کامل.',
			'kw'    => 'تشخیص آیفون اصل',
		],
		'thumb'     => [ 'file' => 'original-iphone-hero.webp', 'alt' => 'آیفون اصل را چگونه تشخیص دهیم؛ بررسی رجیستری و اصالت' ],
		'images'    => [
			'compare'   => [ 'file' => 'original-iphone-compare.webp', 'alt' => 'دو آیفون با ظاهر یکسان؛ تشخیص اصالت با بررسی شناسه‌ها' ],
			'checklist' => [ 'file' => 'original-iphone-checklist.webp', 'alt' => 'چک‌لیست ۱۰ مرحله‌ای تشخیص آیفون اصل' ],
		],
	],
	// برنامه‌ی محتوا، روزِ ۲ (۲۶ مهر ۱۴۰۵): عکس‌های تستِ خودِ فروشگاه؛ IMEI، سریال، ECID و UDID در عکسِ رایانه محو شده‌اند.
	// بالاتر از روزِ ۱ تا لینکِ `{{slug:used-iphone-test}}`ِ آن در همان اجرا پیدا شود.
	// برنامه‌ی محتوا، روزِ ۶ (۲۸ مهر ۱۴۰۵، زودتر — صاحب فروشگاه عکس فرستاد): عکسِ ویترین از صاحب فروشگاه (کارتِ ویزیتِ
	// فروشگاهِ دیگرِ بالای قفسه از کادر بیرون است)، و دو جدولی که خودمان ساختیم. حرفِ اولِ شماره‌ی مدل (M/F/N/P) از اپل؛
	// درباره‌ی فروشگاه فقط آنچه روی کارت و دفترچه‌ی سلامت هست — ادعای فروشِ نات‌اکتیو یا ریفربیش نشده.
	'iphone-sealed-active' => [
		'title'    => 'آکبند، اکتیو، نات‌اکتیو و ریفربیش؛ فرق آیفون‌ها و راه تشخیص',
		'status'   => 'publish',
		'excerpt'  => 'آیفون نات اکتیو یعنی چه و با آکبند، اکتیو و ریفربیش چه فرقی دارد؟ معنی هر وضعیت، اثرش بر ضمانت و قیمت، و راه تشخیص با حرف اول شماره مدل (M، F، N، P) و سابقه قطعات.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'آیفون نات اکتیو', 'آیفون آکبند', 'آیفون اکتیو', 'آیفون ریفربیش', 'شماره مدل آیفون' ],
		'seo'      => [
			'title' => 'آیفون نات اکتیو چیست؟ فرق آکبند، اکتیو و ریفربیش | استوک لند',
			'desc'  => 'آیفون نات اکتیو یعنی چه؟ فرق آکبند، نات‌اکتیو، اکتیو و ریفربیش، اثرش بر ضمانت اپل، و تشخیص با حرف اول شماره مدل (M، F، N، P) و سابقه قطعات.',
			'kw'    => 'آیفون نات اکتیو',
		],
		'thumb'    => [ 'file' => 'iphone-sealed-active-hero.webp', 'alt' => 'فرق آیفون آکبند، اکتیو، نات‌اکتیو و ریفربیش' ],
		'images'   => [
			'compare' => [ 'file' => 'iphone-sealed-active-compare.webp', 'alt' => 'جدول فرق آکبند، نات‌اکتیو، اکتیو و ریفربیش' ],
			'model'   => [ 'file' => 'iphone-sealed-active-model.webp', 'alt' => 'حرف اول شماره مدل آیفون؛ M نو، F بازسازی‌شده، N جایگزین، P سفارشی' ],
		],
	],
	// برنامه‌ی محتوا، روزِ ۵ (۲۸ مهر ۱۴۰۵، یک روز زودتر؛ روزِ ۴ بازبینیِ نقشه‌ی گوگل بود). عکس‌ها (همان روز، صاحب فروشگاه: «عکس‌ها
	// ارتباطی ندارند»): دو صفحه‌ی «سلامت باتری» (۹۶٪ و ۸۰٪ با سیکل) و دفترچه‌ی سلامت با باتریِ تعویضی، به‌جای دو عکسِ فروشگاه از صاحب فروشگاه
	// («از تصویر استفاده کن، بلر نکن» — رضایتِ خودش؛ مشتری پشت به دوربین است) و جدولِ درصدها که خودمان ساختیم. عددهای
	// فنی (مرزِ ۸۰٪، ۵۰۰ و ۱۰۰۰ سیکل) از راهنمای اپل؛ ادعای فروشگاه فقط آنچه در دفترچه‌ی سلامت و کارت دیده می‌شود.
	'iphone-battery-health' => [
		'title'    => 'سلامت باتری آیفون چند درصد خوب است؟ جدول درصدها، سیکل شارژ و حد تعویض',
		'status'   => 'publish',
		'excerpt'  => 'سلامت باتری آیفون چند درصد خوب است؟ ۹۰٪ به بالا عالی، ۸۵ تا ۸۹ خوب، ۸۰ تا ۸۴ قابل قبول و زیر ۸۰٪ وقت تعویض؛ به‌علاوه‌ی سیکل شارژ، باتری تعویضی و نکته‌های خرید آیفون کارکرده.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'سلامت باتری آیفون', 'سیکل شارژ آیفون', 'تعویض باتری آیفون', 'باتری آیفون کارکرده', 'آیفون کارکرده قم' ],
		'seo'      => [
			'title' => 'سلامت باتری آیفون چند درصد خوب است؟ جدول درصدها | استوک لند',
			'desc'  => 'سلامت باتری آیفون چند درصد خوب است؟ ۹۰٪ به بالا عالی، ۸۵ تا ۸۹ خوب، ۸۰ تا ۸۴ قابل قبول، زیر ۸۰٪ تعویض. سیکل شارژ، باتری تعویضی و نکته‌های خرید کارکرده.',
			'kw'    => 'سلامت باتری آیفون',
		],
		'thumb'    => [ 'file' => 'iphone-battery-health-hero.webp', 'alt' => 'سلامت باتری آیفون ۹۶ و ۸۰ درصد با سیکل شارژ در تنظیمات' ],
		'images'   => [
			'scale' => [ 'file' => 'iphone-battery-health-scale.webp', 'alt' => 'جدول سلامت باتری آیفون؛ ۹۰ به بالا عالی تا زیر ۸۰ تعویض' ],
			'shelf' => [ 'file' => 'iphone-battery-health-shelf.webp', 'alt' => 'باتری تعویضی و قطعه ناشناخته در دفترچه سلامت آیفون کارکرده' ],
		],
	],
	// برنامه‌ی محتوا، روزِ ۳ (۲۷ مهر ۱۴۰۵): شرایطِ معاوضه از پاسخِ صاحب فروشگاه (هر برند، ایرادِ قطعه مشکلی نیست به شرطِ
	// روشن شدن، قیمتِ حضوری یا آنلاین، خریدِ نقدی به قیمتِ روز، از شهرهای دیگر با ارسال). دو عکس از چهار عکسِ فرستاده همان
	// عکس‌های روزِ ۲ بودند و دوباره منتشر نشدند. به «تست آیفون کارکرده» و «آیفون اقساطی» لینک می‌دهد که از قبل منتشر شده‌اند.
	'trade-in-qom' => [
		'title'    => 'معاوضه آیفون در قم؛ گوشیِ قدیمی از هر برند، قیمتِ حضوری یا آنلاین',
		'status'   => 'publish',
		'excerpt'  => 'معاوضه آیفون در قم در استوک لند: گوشیِ قدیمی از هر برندی، حتی با قطعه‌ی تعویضی، قیمتِ حضوری یا آنلاین، آیفونِ نو یا کارکرده با دفترچه‌ی سلامت، خریدِ نقدی به قیمتِ روز و امکانِ معاوضه از شهرهای دیگر.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'معاوضه آیفون قم', 'معاوضه گوشی قدیمی', 'فروش گوشی کارکرده', 'خرید گوشی دست دوم قم', 'آیفون کارکرده قم' ],
		'seo'      => [
			'title' => 'معاوضه آیفون در قم؛ گوشی قدیمی از هر برند | استوک لند',
			'desc'  => 'معاوضه آیفون در قم: گوشی قدیمی از هر برند، حتی با قطعه تعویضی؛ قیمت حضوری یا آنلاین، آیفون نو یا کارکرده با دفترچه سلامت، خرید نقدی و ارسال از همه شهرها.',
			'kw'    => 'معاوضه آیفون قم',
		],
		'thumb'    => [ 'file' => 'trade-in-qom-hero.webp', 'alt' => 'معاوضه گوشی قدیمی با آیفون در استوک لند قم' ],
		'images'   => [
			'pick' => [ 'file' => 'trade-in-qom-pick.webp', 'alt' => 'انتخاب آیفون کارکرده برای معاوضه از سایت استوک لند' ],
		],
	],
	'used-iphone-test' => [
		'title'    => 'تست آیفون کارکرده؛ دفترچه سلامت ۲۱ بندی و چطور خودتان تست کنید',
		'status'   => 'publish',
		'excerpt'  => 'تست آیفون کارکرده در استوک لند: سلامت و سیکل باتری، بررسی با رایانه، فیس‌آیدی و دوربین‌ها، و دفترچه سلامت ۲۱ بندی برای هر گوشی؛ به‌علاوه‌ی ۸ تستی که خودتان پیش از خرید انجام دهید.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'تست آیفون کارکرده', 'سلامت باتری آیفون', 'دفترچه سلامت آیفون', 'قطعه ناشناخته آیفون', 'آیفون کارکرده قم' ],
		'seo'      => [
			'title' => 'تست آیفون کارکرده؛ باتری، فیس‌آیدی و دفترچه سلامت | استوک لند',
			'desc'  => 'تست آیفون کارکرده قبل از خرید: سلامت و سیکل باتری، قطعه ناشناخته، فیس‌آیدی، دوربین و آیکلود. دفترچه سلامت ۲۱ بندی استوک لند و ۸ تست ساده برای خودتان.',
			'kw'    => 'تست آیفون کارکرده',
		],
		'thumb'    => [ 'file' => 'used-iphone-test-hero.webp', 'alt' => 'تست آیفون کارکرده؛ سلامت باتری در استوک لند' ],
		'images'   => [
			'pc'        => [ 'file' => 'used-iphone-test-pc.webp', 'alt' => 'بررسی آیفون کارکرده با رایانه در استوک لند' ],
			'faceid'    => [ 'file' => 'used-iphone-test-faceid.webp', 'alt' => 'تست فیس‌آیدی و دوربین جلوی آیفون کارکرده' ],
			'checklist' => [ 'file' => 'used-iphone-test-checklist.webp', 'alt' => 'دفترچه سلامت آیفون کارکرده در ۲۱ بند' ],
		],
	],
	// برنامه‌ی محتوا، روزِ ۱ (۲۵ مهر ۱۴۰۵): عکس‌های خودِ فروشگاه از صاحب فروشگاه؛ چهره‌ی مشتری‌ها در عکسِ ویترین محو
	// شده. بالاتر از «آیفون اقساطی» تا لینکِ `{{slug:used-iphone-qom}}`ِ آن در همان اجرا پیدا شود.
	'used-iphone-qom' => [
		'title'    => 'خرید آیفون کارکرده در قم؛ تست‌شده، با دفترچه سلامت و مهلت تست',
		'status'   => 'publish',
		'excerpt'  => 'خرید آیفون کارکرده در قم از استوک لند: دفترچه سلامت (باتری، رجیستری، تست قطعات)، دست‌کم یک هفته مهلت تست، اقساط ۱ تا ۱۲ ماهه و ارسال به همه شهرها؛ با چک‌لیست بررسی پیش از خرید.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'آیفون کارکرده قم', 'خرید آیفون کارکرده', 'آیفون استوک', 'استوک لند قم', 'دفترچه سلامت آیفون' ],
		'seo'      => [
			'title' => 'خرید آیفون کارکرده در قم؛ دفترچه سلامت و مهلت تست | استوک لند',
			'desc'  => 'خرید آیفون کارکرده در قم از استوک لند: تست کامل و دفترچه سلامت، دست‌کم یک هفته مهلت تست، اقساط ۱ تا ۱۲ ماهه و ارسال به همه شهرها. چک‌لیست خرید را بخوانید.',
			'kw'    => 'آیفون کارکرده قم',
		],
		'thumb'    => [ 'file' => 'used-iphone-qom-hero.webp', 'alt' => 'خرید آیفون کارکرده در قم از استوک لند' ],
		'images'   => [
			'store'  => [ 'file' => 'used-iphone-qom-store.webp', 'alt' => 'فروشگاه استوک لند در بازار سلام قم' ],
			'health' => [ 'file' => 'used-iphone-qom-health.webp', 'alt' => 'دفترچه سلامت آیفون کارکرده؛ تست باتری، رجیستری و قطعات' ],
			'colors' => [ 'file' => 'used-iphone-qom-colors.webp', 'alt' => 'آیفون‌های کارکرده در رنگ‌های مختلف در استوک لند قم' ],
		],
	],
	// پیش‌نویسِ ۱۵ مهر (از پیشخوان): بازنویسی و انتشار (صاحب فروشگاه، ۲۴ مهر). نسخه‌ی قبلی رقیب‌ها را معرفی می‌کرد و
	// لینک‌های بیرونیِ بی‌متن داشت؛ این نسخه فقط شرایطِ واقعیِ استوک لند (`stlh_store_facts`) و عکس‌های خودِ فروشگاه را دارد.
	'iphone-installment-qom' => [
		'post_id'  => 44849,
		'title'    => 'آیفون اقساطی در قم؛ شرایط خرید قسطی آیفون نو و کارکرده در استوک لند',
		'status'   => 'publish',
		'excerpt'  => 'خرید آیفون اقساطی در قم از استوک لند: اقساط ۱ تا ۱۲ ماهه با ضمانت طلا یا چک معتبر یا اسنپ‌پی، برای آیفون نو و کارکرده؛ مراحل خرید، مزایا و معایب و نکات پیش از خرید.',
		'category' => [ 'id' => 2968 ],
		'tags'     => [ 'آیفون اقساطی در قم', 'خرید اقساطی آیفون', 'آیفون قسطی', 'استوک لند قم', 'آیفون کارکرده' ],
		'seo'      => [
			'title' => 'آیفون اقساطی در قم؛ ۱ تا ۱۲ ماهه با ضمانت طلا یا چک | استوک لند',
			'desc'  => 'خرید آیفون اقساطی در قم از استوک لند: آیفون نو و کارکرده با اقساط ۱ تا ۱۲ ماهه، ضمانت طلا یا چک معتبر و اسنپ‌پی. مراحل خرید و نکات مهم را بخوانید.',
			'kw'    => 'آیفون اقساطی در قم',
		],
		'thumb'    => [ 'file' => 'iphone-installment-qom-hero.webp', 'alt' => 'آیفون اقساطی در قم؛ خرید قسطی آیفون نو و کارکرده از استوک لند' ],
		'images'   => [
			'models' => [ 'file' => 'iphone-installment-qom-models.webp', 'alt' => 'کدام آیفون را قسطی بخریم؛ آیفون نارنجی و نقره‌ای' ],
			'steps' => [ 'file' => 'iphone-installment-qom-steps.webp', 'alt' => 'مراحل خرید آیفون اقساطی در استوک لند در ۵ قدم' ],
			'boxes' => [ 'file' => 'stockland-iphone-boxes.webp', 'alt' => 'جعبه‌های آیفون نو در استوک لند قم' ],
			'box'   => [ 'file' => 'stockland-iphone-box.webp', 'alt' => 'آیفون روی جعبه‌ی اصلی در استوک لند' ],
		],
	],
];

add_action( 'init', 'stlh_posts_apply', 40 );

/**
 * کشِ اشیا (memcached) بعد از نوشتن: درخواستی که هم‌زمان نسخه‌ی قدیم را از پایگاه داده خوانده بود (مثلاً گرم‌کنِ کش
 * بعد از پاک‌سازیِ LiteSpeed) ممکن است بعد از `clean_post_cache` ما همان را دوباره در کش بگذارد — ۲۴ مهر مقاله‌ی ۴۴۳۴۱
 * با متنِ قدیم ماند در حالی که پایگاه داده و نسخه‌ها متنِ تازه را داشتند. پس یک بار دیگر، کمی بعد، کش پاک می‌شود؛
 * و با هر نسخه‌ی افزونه هم یک بار برای همه‌ی مقاله‌های این فایل.
 */
add_action( 'stlh_posts_recache', 'stlh_posts_recache' );
function stlh_posts_recache( array $ids = [] ): void {
	if ( ! $ids ) {
		$ids = array_filter( array_map( static fn( $x ) => (int) ( $x['id'] ?? 0 ), (array) get_option( 'stlh_posts_state', [] ) ) );
	}
	foreach ( $ids as $id ) {
		clean_post_cache( (int) $id );
		wp_cache_delete( (int) $id, 'post_meta' );
		do_action( 'litespeed_purge_post', (int) $id );
	}
	do_action( 'litespeed_purge_url', home_url( '/' ) );
}

/**
 * مقاله‌ی تازه یا ویرایش‌شده فقط صفحه‌های وبلاگ (فهرستِ «تازه‌های خبری» در همه‌ی مقاله‌ها) و صفحه‌ی اصلی را کهنه می‌کند،
 * نه محصول و فروشگاه. تا ۲۶ مهر ۱۴۰۵ هر نوشتن و هر نسخه‌ی افزونه **کلِ** کشِ LiteSpeed را پاک می‌کرد و صفحه‌ی بعدیِ
 * هر مشتری ۲٫۵ تا ۳ ثانیه ساخته می‌شد (گرم‌کن روی این هاست عمداً خاموش است، `warm.php`) — صاحب فروشگاه: «سرعتِ لودِ سایت».
 */
function stlh_posts_purge_blog(): void {
	do_action( 'litespeed_purge_posttype', 'post' );
	do_action( 'litespeed_purge_url', home_url( '/' ) );
}

function stlh_posts_apply(): void {
	if ( get_option( 'stlh_posts_recache_ver' ) !== STLH_VER ) {
		update_option( 'stlh_posts_recache_ver', STLH_VER, false );
		stlh_posts_recache();
	}
	$state = (array) get_option( 'stlh_posts_state', [] );
	$todo  = [];
	foreach ( STLH_POSTS as $slug => $def ) {
		$file = STLH_DIR . 'content/posts/' . $slug . '.html';
		// عکس‌ها هم در نشانِ تغییر: عکسِ عوض‌شده با همان نام (۲۴ مهر، ویترینِ بی‌آدم) باید دوباره برود
		$imgs = '';
		foreach ( array_merge( [ $def['thumb'] ], array_values( $def['images'] ) ) as $img ) {
			$f     = STLH_DIR . 'assets/posts/' . $img['file'];
			$imgs .= is_readable( $f ) ? md5_file( $f ) : '';
		}
		$hash = md5( wp_json_encode( $def ) . ( is_readable( $file ) ? md5_file( $file ) : '' ) . $imgs );
		if ( ( $state[ $slug ]['hash'] ?? '' ) !== $hash ) {
			$todo[ $slug ] = $hash;
		}
	}
	if ( ! $todo ) {
		return;
	}
	if ( ! add_option( 'stlh_posts_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'stlh_posts_lock' ) < 120 ) {
			return;
		}
		update_option( 'stlh_posts_lock', time(), false );
	}
	@set_time_limit( 90 );
	foreach ( $todo as $slug => $hash ) {
		try {
			$state[ $slug ] = [ 'hash' => $hash, 'at' => gmdate( 'c' ) ] + stlh_post_upsert( $slug, STLH_POSTS[ $slug ], $state[ $slug ] ?? [] );
		} catch ( Throwable $e ) {
			$state[ $slug ] = [ 'hash' => '', 'at' => gmdate( 'c' ), 'error' => $e->getMessage() ];
		}
	}
	update_option( 'stlh_posts_state', $state, false );
	delete_option( 'stlh_posts_lock' );
	$ids = array_values( array_filter( array_map( static fn( $slug ) => (int) ( $state[ $slug ]['id'] ?? 0 ), array_keys( $todo ) ) ) );
	if ( $ids ) {
		wp_schedule_single_event( time() + 90, 'stlh_posts_recache', [ $ids ] );
	}
}

/** فایلِ `assets/posts/<file>` در کتابخانه‌ی رسانه (یک بار) → شناسه */
function stlh_post_media( string $file, string $alt, int $parent ): int {
	$map = (array) get_option( 'stlh_post_media', [] );
	$src = STLH_DIR . 'assets/posts/' . $file;
	$key = $file . ':' . md5_file( $src );
	if ( ! empty( $map[ $key ] ) && get_post( (int) $map[ $key ] ) ) {
		update_post_meta( (int) $map[ $key ], '_wp_attachment_image_alt', $alt );
		return (int) $map[ $key ];
	}
	$up = wp_upload_bits( $file, null, (string) file_get_contents( $src ) );
	if ( ! empty( $up['error'] ) ) {
		throw new RuntimeException( 'upload ' . $file . ': ' . $up['error'] );
	}
	$type = wp_check_filetype( $up['file'] )['type'] ?: 'image/webp';
	$id   = wp_insert_attachment( [ 'post_mime_type' => $type, 'post_title' => $alt, 'post_status' => 'inherit' ], $up['file'], $parent, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'attach ' . $file . ': ' . $id->get_error_message() );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	$map[ $key ] = $id;
	update_option( 'stlh_post_media', $map, false );
	return $id;
}

function stlh_post_upsert( string $slug, array $def, array $prev ): array {
	$file = STLH_DIR . 'content/posts/' . $slug . '.html';
	if ( ! is_readable( $file ) ) {
		throw new RuntimeException( 'missing content/posts/' . $slug . '.html' );
	}
	global $wpdb;
	if ( ! empty( $prev['id'] ) ) {
		$post = get_post( (int) $prev['id'] );
		// صاحب فروشگاه پاکش کرده (یا به زباله‌دان برده)؟ دوباره نمی‌سازیم
		if ( ! $post || 'trash' === $post->post_status ) {
			return [ 'id' => (int) $prev['id'], 'skipped' => 'deleted in admin', 'modified' => $prev['modified'] ?? '' ];
		}
	} elseif ( ! empty( $def['post_id'] ) ) {
		// مقاله‌ای که از قبل در سایت هست
		$post = get_post( (int) $def['post_id'] );
		if ( ! $post || 'post' !== $post->post_type || 'trash' === $post->post_status ) {
			throw new RuntimeException( 'post ' . $def['post_id'] . ' not found' );
		}
	} else {
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY ID LIMIT 1", $slug ) );
		$post  = $found ? get_post( (int) $found ) : null;
	}
	// صاحب فروشگاه بعد از ما ویرایش کرده؟ دست نمی‌زنیم
	if ( $post && ! empty( $prev['modified'] ) && $post->post_modified_gmt !== $prev['modified'] ) {
		return [ 'id' => $post->ID, 'skipped' => 'edited in admin', 'modified' => $prev['modified'] ];
	}
	$id = $post ? $post->ID : wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => $def['title'] ], true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}

	$html = (string) file_get_contents( $file );
	foreach ( $def['images'] as $k => $img ) {
		$aid  = stlh_post_media( $img['file'], $img['alt'], $id );
		$src  = (string) wp_get_attachment_image_url( $aid, 'large' );
		$html = str_replace(
			'{{img:' . $k . '}}',
			'<!-- wp:image {"id":' . $aid . ',"sizeSlug":"large","align":"center"} -->' . "\n"
				. '<figure class="wp-block-image aligncenter size-large"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $img['alt'] ) . '" class="wp-image-' . $aid . '"/></figure>' . "\n"
				. '<!-- /wp:image -->',
			$html
		);
	}
	$html = preg_replace_callback( '/\{\{cat:([^}]+)\}\}/u', static function ( $m ) {
		$t    = get_term_by( 'name', $m[1], 'product_cat' );
		$link = $t ? get_term_link( $t ) : '';
		return esc_url( is_string( $link ) && $link ? $link : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ) );
	}, $html );

	$html = preg_replace_callback( '/\{\{post:(\d+)\}\}/', static fn( $m ) => esc_url( (string) get_permalink( (int) $m[1] ) ), $html );
	// `{{slug:نامک}}` → مقاله‌ی دیگرِ همین فایل که شناسه‌اش پیش از ساخته شدن معلوم نیست (لینکِ مقاله‌ی قبلی به تازه)
	$html = preg_replace_callback( '/\{\{slug:([a-z0-9-]+)\}\}/', static function ( $m ) use ( $wpdb ) {
		$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status = 'publish' ORDER BY ID LIMIT 1", $m[1] ) );
		return esc_url( $pid ? (string) get_permalink( $pid ) : home_url( '/' . $m[1] . '/' ) );
	}, $html );

	$cat = ! empty( $def['category']['id'] ) ? get_term( (int) $def['category']['id'], 'category' ) : get_term_by( 'slug', $def['category']['slug'], 'category' );
	$cat = $cat instanceof WP_Term ? $cat : null;
	if ( ! $cat && ! empty( $def['category']['slug'] ) ) {
		$made = wp_insert_term( $def['category']['name'], 'category', [ 'slug' => $def['category']['slug'] ] );
		$cat  = is_wp_error( $made ) ? null : get_term( (int) $made['term_id'], 'category' );
	}
	// درخواستِ بازدیدکننده‌ی ناشناس: kses بلوک‌ها و جدول را دست نبرد (متن از مخزنِ خودمان است)
	kses_remove_filters();
	$r = wp_update_post( wp_slash( [
		'ID'            => $id,
		'post_title'    => $def['title'],
		'post_name'     => ! empty( $def['keep_slug'] ) && $post ? $post->post_name : $slug,
		'post_content'  => $html,
		'post_excerpt'  => $def['excerpt'] ?? '',
		// پیش‌نویس فقط با تعریفِ مخزن منتشر می‌شود؛ وضعیتی که در پیشخوان داده شده (منتشر/زمان‌بندی) دست نمی‌خورد
		'post_status'   => $post && 'draft' !== $post->post_status ? $post->post_status : $def['status'],
		'post_category' => $cat ? [ (int) $cat->term_id ] : [],
	] ), true );
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		kses_init_filters();
	}
	if ( is_wp_error( $r ) ) {
		throw new RuntimeException( $r->get_error_message() );
	}
	wp_set_post_tags( $id, $def['tags'], false );
	set_post_thumbnail( $id, stlh_post_media( $def['thumb']['file'], $def['thumb']['alt'], $id ) );
	update_post_meta( $id, '_yoast_wpseo_title', $def['seo']['title'] );
	update_post_meta( $id, '_yoast_wpseo_metadesc', $def['seo']['desc'] );
	update_post_meta( $id, '_yoast_wpseo_focuskw', $def['seo']['kw'] );
	clean_post_cache( $id );
	stlh_posts_purge_blog();
	return [ 'id' => $id, 'status' => get_post_status( $id ), 'modified' => (string) get_post_field( 'post_modified_gmt', $id ) ];
}

/**
 * کادرِ «ما را دنبال کنید» پایینِ هر مقاله (صاحب فروشگاه، ۲۴ مهر ۱۴۰۵: «برای تبلیغِ بهترِ کانالِ تلگرام و پیجِ
 * اینستاگرام، با لینک، شلوغ نشود»). فقط صفحه‌ی خودِ مقاله، نه فهرست‌ها و خوراک؛ نشانی‌ها از `stlh_store_facts()`.
 */
add_filter( 'the_content', static function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || ! function_exists( 'stlh_store_facts' ) ) {
		return $content;
	}
	$f  = stlh_store_facts();
	$ig = 'https://instagram.com/' . rawurlencode( (string) $f['instagram'] );
	$tg = 'https://t.me/' . rawurlencode( (string) $f['telegram'] );
	$a  = 'display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;color:#fff;font-weight:700;font-size:14px;text-decoration:none;';
	return $content
		. '<aside class="stlh-follow" style="margin:28px 0 8px;padding:14px 16px;border:1px solid #e0e0e6;border-radius:12px;background:#fafbfc;text-align:center">'
		. '<p style="margin:0 0 10px;font-size:14px;color:#3f4064">' . esc_html( stlh_label( 'follow' ) ) . '</p>'
		. '<a href="' . esc_url( $ig ) . '" target="_blank" rel="noopener" style="' . $a . 'background:linear-gradient(45deg,#f09433,#dc2743,#bc1888);margin:4px">اینستاگرام ‎@' . esc_html( (string) $f['instagram'] ) . '</a>'
		. '<a href="' . esc_url( $tg ) . '" target="_blank" rel="noopener" style="' . $a . 'background:#229ed9;margin:4px">کانال تلگرام ‎@' . esc_html( (string) $f['telegram'] ) . '</a>'
		. '</aside>';
}, 20 );


/**
 * پیش‌نویس‌هایی که به زباله‌دان می‌روند (برگشت‌پذیر، از «نوشته‌ها ← زباله‌دان»). فقط اگر هنوز پیش‌نویس‌اند.
 * • ۴۴۸۴۶ «خرید اقساطی آیفون در قم»: همان موضوع و کلیدواژه‌ی «آیفون اقساطی در قم» (۴۴۸۴۹)؛ دو صفحه‌ی یک سایت
 *   سرِ یک جست‌وجو با هم رقابت می‌کنند و هیچ‌کدام بالا نمی‌آید. رقیب‌ها را هم معرفی می‌کرد (صاحب فروشگاه، ۲۴ مهر:
 *   «اگر از دیدِ کارشناسی اشکال دارد پاکش کن»).
 * • بقیه‌ی پیش‌نویس‌ها (۱۵ مهر): صاحب فروشگاه، ۲۴ مهر: «همه‌ی پیش‌نویس‌ها را پاک کن، نیازی نداریم». موضوع‌هایشان در
 *   برنامه‌ی محتوا (`docs/seo-plan.md`) از نو و درست نوشته می‌شوند.
 */
const STLH_POSTS_TRASH = [ 44846, 44843, 44840, 44837, 44834, 44831 ];

add_action( 'init', static function (): void {
	$hash = md5( implode( ',', STLH_POSTS_TRASH ) );
	$prev = (array) get_option( 'stlh_posts_trash', [] );
	if ( ( $prev['hash'] ?? '' ) === $hash ) {
		return;
	}
	$done = [];
	foreach ( STLH_POSTS_TRASH as $id ) {
		$st          = get_post_status( $id );
		$done[ $id ] = 'draft' === $st ? ( wp_trash_post( $id ) ? 'trashed' : 'failed' ) : (string) $st;
	}
	update_option( 'stlh_posts_trash', [ 'hash' => $hash, 'at' => gmdate( 'c' ), 'items' => $done ], false );
}, 41 );
