<?php

declare(strict_types=1);

namespace SqlParser\Table;

use RuntimeException;

/**
 * Stores parse tables as native PHP codec bytes and reuses identical file contents.
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
        $bytes = $this->codec->encode($table);
        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException("Cannot write parse table to {$path}");
        }
    }

    /**
     * Reads a table from a file, reusing it when the same file was read before.
     *
     * The file is read on every call and recognized by a fast non-cryptographic digest of its
     * contents, which only has to tell file contents apart within one process.
     *
     * @param string $path File written by save()
     *
     * @return ParseTable The table
     *
     * @throws RuntimeException When the file is missing or is not a readable encoded table
     */
    public function load(string $path): ParseTable
    {
        $compressed = is_file($path) ? file_get_contents($path) : false;
        if ($compressed === false) {
            throw new RuntimeException("Parse table not found: {$path}");
        }
        $key = $path . ':' . hash('xxh128', $compressed);
        if (isset(self::$loaded[$key])) {
            return self::$loaded[$key];
        }
        $bytes = str_starts_with($compressed, TableCodec::MAGIC) ? $compressed : $this->inflate($compressed);
        if ($bytes === null) {
            throw new RuntimeException("Parse table is not a readable encoded table: {$path}");
        }

        return self::$loaded[$key] = $this->codec->decode($bytes);
    }

    /**
     * Reads legacy compressed caches when the optional zlib extension is available.
     * New and shipped artifacts use uncompressed codec bytes and never call this method.
     *
     * @param string $compressed Bytes read from the file
     *
     * @return string|null The inflated bytes, or null when inflating fails
     */
    public function inflate(string $compressed): ?string
    {
        if (!function_exists('gzinflate')) {
            return null;
        }
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
