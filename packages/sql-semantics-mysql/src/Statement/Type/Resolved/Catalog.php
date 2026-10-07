<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Resolved;

use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The character sets and collations of every MySQL release, read from the catalogs generated from the servers.
 *
 * Names are kept in their 8.0 spelling: the `utf8` of 5.6 and 5.7 is `utf8mb3`. Each character set
 * and collation is created once, from the newest release that has it, so two lookups of the same
 * name answer the same object.
 *
 * @visibility SqlSemantics\Platform\MySql\Statement\Type\Resolved
 */
final class Catalog
{
    use Snapshot;

    private static ?self $instance = null;

    /**
     * @var array<string, Charset>
     */
    public readonly array $charsets;

    /**
     * @var array<string, Collation>
     */
    public readonly array $collations;

    /**
     * @var array<string, array<string, string>> The default collation of each character set, by release
     */
    public readonly array $defaults;

    /**
     * @var array<string, list<string>> The releases that have each collation
     */
    public readonly array $releases;

    /**
     * @param array<string, array{charsets: array<string, array{string, int}>, collations: array<string, array{string, int, bool}>}> $catalogs The catalog of each release
     */
    public function __construct(array $catalogs)
    {
        $charsets = [];
        $collations = [];
        $defaults = [];
        $releases = [];
        foreach (array_reverse($catalogs, true) as $release => $catalog) {
            foreach ($catalog['charsets'] as $name => [$default, $length]) {
                $name = self::canonical($name);
                $charsets[$name] ??= new Charset($name, $length);
                $defaults[$release][$name] = self::canonical($default);
            }
            foreach ($catalog['collations'] as $name => [$charset, $id, $padSpace]) {
                $name = self::canonical($name);
                $collations[$name] ??= new Collation($name, $id, $charsets[self::canonical($charset)], $padSpace);
                $releases[$name] = [$release, ...($releases[$name] ?? [])];
            }
        }
        ksort($defaults);
        $this->charsets = $charsets;
        $this->collations = $collations;
        $this->defaults = $defaults;
        $this->releases = $releases;
    }

    /**
     * Answers the catalog of every supported release.
     */
    public static function shared(): self
    {
        if (self::$instance === null) {
            $catalogs = [];
            foreach (GrammarRelease::cases() as $release) {
                $file = dirname(__DIR__, 4) . '/resources/collations/' . $release->value . '.php';
                if (is_file($file)) {
                    /** @var array{charsets: array<string, array{string, int}>, collations: array<string, array{string, int, bool}>} $catalog */
                    $catalog = require $file;
                    $catalogs[$release->value] = $catalog;
                }
            }
            self::$instance = new self($catalogs);
        }

        return self::$instance;
    }

    /**
     * Spells a character set or collation name as 8.0 does: lower case, `utf8` as `utf8mb3`.
     */
    public static function canonical(string $name): string
    {
        $name = strtolower($name);

        return $name === 'utf8' || str_starts_with($name, 'utf8_') ? 'utf8mb3' . substr($name, 4) : $name;
    }
}
