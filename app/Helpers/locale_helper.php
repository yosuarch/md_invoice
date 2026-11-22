<?php

/**
 * Screen language (English / Indonesian). Best practice in CodeIgniter 4: every UI text comes from the language
 * files app/Language/{locale}/<Module>.php via lang('Module.key'); the locale is chosen per user and set on the
 * request by App\Filters\LocaleFilter. Code, keys and stored data stay English; printed documents stay Indonesian.
 */

if (! function_exists('app_locales')) {
    /**
     * Supported screen languages: locale => native name.
     *
     * @return array<string, string>
     */
    function app_locales(): array
    {
        return ['id' => 'Bahasa Indonesia', 'en' => 'English'];
    }
}

if (! function_exists('current_locale_choice')) {
    /**
     * The saved language choice of the current user (or guest session), null when none / unsupported.
     *
     * @return string|null
     */
    function current_locale_choice(): ?string
    {
        $locale = null;

        if (function_exists('auth') && auth()->loggedIn()) {
            $locale = auth()->user()->locale ?? null;
        }

        $locale ??= session('locale');

        return is_string($locale) && isset(app_locales()[$locale]) ? $locale : null;
    }
}

if (! function_exists('js_strings')) {
    /**
     * Texts used by the JavaScript files (app/Language/{locale}/Js.php), for window.App.lang.
     *
     * @return array<string, string>
     */
    function js_strings(): array
    {
        $strings = [];

        foreach (array_keys(require APPPATH . 'Language/en/Js.php') as $key) {
            $strings[$key] = lang('Js.' . $key);
        }

        return $strings;
    }
}
