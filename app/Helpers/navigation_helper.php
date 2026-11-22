<?php

/**
 * Helpers for rendering navigation menus defined in Config\Navigation.
 */

if (! function_exists('nav_is_active')) {
    /**
     * Whether a navigation item matches the current request URI.
     *
     * @param array{uri: string, exact?: bool} $item Navigation item from Config\Navigation.
     *
     * @return bool True when the item should be highlighted as active.
     */
    function nav_is_active(array $item): bool
    {
        $uri = trim($item['uri'], '/');

        if ($uri === '') {
            return url_is('/');
        }

        if (url_is($uri)) {
            return true;
        }

        return empty($item['exact']) && url_is($uri . '/*');
    }
}

if (! function_exists('nav_visible_items')) {
    /**
     * Filter navigation items to those the current user is allowed to see.
     *
     * @param list<array{permission?: string}> $items Navigation items.
     *
     * @return list<array<string, mixed>> Items without a permission, or whose permission the user has.
     */
    function nav_visible_items(array $items): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => ! isset($item['permission']) || $user->can($item['permission']),
        ));
    }
}
