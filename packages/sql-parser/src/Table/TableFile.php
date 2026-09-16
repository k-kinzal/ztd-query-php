<?php

declare(strict_types=1);

namespace SqlParser\Table;

use RuntimeException;

/**
 * Stores parse tables as deflated files and loads them once per process.
 *
 * @visibility root
 */
final class TableFile
{
    /**
     * @var array<string, ParseTable>
     */
    private static array $loaded = [];

    /**
     * @param TableCodec $codec Converts between tables and bytes
     */
    public function __construct(private readonly TableCodec $codec = new TableCodec())
    {
    }

    /**
     * Writes a table to a file.
     *
     * @param ParseTable $table Table to store
     * @param string $path Where to write it
     *
     * @throws RuntimeException When the file cannot be written
     */
    public function save(ParseTable $table, string $path): void
    {
        $bytes = gzdeflate($this->codec->encode($table), 9);
        if ($bytes === false || file_put_contents($path, $bytes) === false) {
            throw new RuntimeException("Cannot write parse table to {$path}");
        }
    }

    /**
     * Reads a table from a file, reusing it when the same file was read before.
     *
     * @param string $path File written by save()
     *
     * @return ParseTable The table
     *
     * @throws RuntimeException When the file is missing, not deflated, or not an encoded table
     */
    public function load(string $path): ParseTable
    {
        if (isset(self::$loaded[$path])) {
            return self::$loaded[$path];
        }
        $compressed = is_file($path) ? file_get_contents($path) : false;
        if ($compressed === false) {
            throw new RuntimeException("Parse table not found: {$path}");
        }
        $bytes = $this->inflate($compressed);
        if ($bytes === null) {
            throw new RuntimeException("Parse table is not a deflated file: {$path}");
        }

        return self::$loaded[$path] = $this->codec->decode($bytes);
    }

    /**
     * Inflates deflated bytes, quietly answering null when they are not deflated.
     *
     * @param string $compressed Bytes read from the file
     *
     * @return string|null The inflated bytes, or null when inflating fails
     */
    public function inflate(string $compressed): ?string
    {
        set_error_handler(static fn (): bool => true);
        try {
            $bytes = gzinflate($compressed);
        } finally {
            restore_error_handler();
        }

        return $bytes === false ? null : $bytes;
    }

    /**
     * Forgets every table loaded so far, so a rewritten file is read again.
     */
    public static function forget(): void
    {
        self::$loaded = [];
    }
}
