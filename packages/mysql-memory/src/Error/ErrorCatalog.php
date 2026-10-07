<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

/**
 * The SQLSTATE and message format of every error the emulator raises, read once from resources/errors.php.
 *
 * @visibility MySqlMemory\Error
 */
final class ErrorCatalog
{
    private static ?self $instance = null;

    /**
     * @param array<int, array{string, string}> $entries The SQLSTATE and format of each error number
     */
    public function __construct(public readonly array $entries)
    {
    }

    /**
     * Answers the catalog of the installed resource.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            /** @var array<int, array{string, string}> $entries */
            $entries = require dirname(__DIR__, 2) . '/resources/errors.php';
            self::$instance = new self($entries);
        }

        return self::$instance;
    }

    /**
     * Answers the SQLSTATE and format of an error number.
     *
     * @return array{string, string}
     */
    public function entry(int $code): array
    {
        return $this->entries[$code] ?? ['HY000', 'Unknown error'];
    }
}
