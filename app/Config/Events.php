<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

/*
 * PHP 8.6 workaround (CodeIgniter 4.7.6):
 * PHP 8.6 emits an E_WARNING when a SessionHandlerInterface class lacks create_sid()/validateId()
 * ("... will be required in PHP 9.0"). CI turns warnings into exceptions, which breaks every
 * session (and so Shield login). Preload CI's session handlers while ignoring only that warning.
 * Remove once CodeIgniter ships a fix.
 */
Events::on('pre_system', static function (): void {
    if (PHP_VERSION_ID < 80600) {
        return;
    }

    set_error_handler(
        static fn (int $severity, string $message): bool => $severity === E_WARNING
            && str_contains($message, 'implementing SessionHandlerInterface is missing'),
    );

    try {
        class_exists(\CodeIgniter\Session\Handlers\BaseHandler::class);
        class_exists(config('Session')->driver);
    } finally {
        restore_error_handler();
    }
});

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});
