<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap: CodeIgniter's test bootstrap plus the PHP 8.6 session handler workaround
 * (see app/Config/Events.php). The tests mock the session with ArrayHandler, whose class declaration raises an
 * E_WARNING on PHP 8.6 that CodeIgniter would turn into an exception; preload the handlers while ignoring only it.
 */
require __DIR__ . '/../vendor/autoload.php';

if (PHP_VERSION_ID >= 80600) {
    set_error_handler(
        static fn (int $severity, string $message): bool => $severity === E_WARNING
            && str_contains($message, 'implementing SessionHandlerInterface is missing'),
    );

    try {
        class_exists(CodeIgniter\Session\Handlers\BaseHandler::class);
        class_exists(CodeIgniter\Session\Handlers\ArrayHandler::class);
        class_exists(CodeIgniter\Session\Handlers\FileHandler::class);
    } finally {
        restore_error_handler();
    }
}

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
