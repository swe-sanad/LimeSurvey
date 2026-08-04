<?php

/**
 * Supplemental translations for the React survey-editor (theme options, presentation, etc.),
 * merged into the /i18n/<lang> payload served by TranslationMoToJson.
 *
 * The editor renders labels via its client-side i18next t() from raw theme config.xml `title=` /
 * `category=` attributes. Some of those strings have no entry in locale/<lang>/<lang>.mo (or were
 * never harvested into the gettext catalog at all, because xgettext does not scan XML title
 * attributes). i18next is configured to fall back to the key itself, so a missing entry renders as
 * the raw ENGLISH msgid inside an otherwise-translated (e.g. Arabic/RTL) UI.
 *
 * This fork-maintained map only ADDS keys the .mo is missing (upstream deferred config.xml
 * harvesting), so it is upgrade-safe. Keyed by language code, then English msgid => translation.
 */

return [
    'ar' => [
        'Fonts'                   => 'الخطوط',
        'Theme color'             => 'لون السمة',
        'Corner radius'           => 'استدارة الزوايا',
        'Display options'         => 'خيارات العرض',
        "Show 'Clear all' button" => 'إظهار زر ‘مسح الكل’',
        // The first %s is an English type word ("background"/"logo") injected from the config
        // title; localizing it cleanly needs a front-end rebuild, so it stays English for now.
        'Change or upload your own %s image (max. file size %s)' => 'غيّر أو ارفع صورة %s الخاصة بك (بحد أقصى لحجم الملف %s)',
    ],
];
