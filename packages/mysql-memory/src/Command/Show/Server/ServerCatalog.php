<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

/**
 * The storage engines, plugins, privileges, character set descriptions and collation sort lengths of the emulated server.
 *
 * Versioned definitions determine engines, plugins and privileges. Character sets and collations
 * reuse the semantic type catalog; sort lengths follow their weighting algorithms.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/storage-engines.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-plugins.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-privileges.html.
 *
 * @visibility MySqlMemory
 */
final class ServerCatalog
{
    /**
     * The other names of the storage engines, by lower-case alias.
     */
    public const ALIASES = ['innobase' => 'InnoDB', 'heap' => 'MEMORY', 'merge' => 'MRG_MYISAM'];

    private static ?self $instance = null;

    /**
     * @param list<array{string, string, string, string|null, string|null, string|null}> $engines The rows of SHOW ENGINES
     * @param list<array{string, string, string, string|null, string}> $plugins The rows of SHOW PLUGINS
     * @param list<array{string, string, string}> $privileges The rows of SHOW PRIVILEGES
     * @param array<string, string> $charsets The description of each character set, by name
     * @param array<string, int> $collations The sort length of each collation, by name
     */
    public function __construct(public readonly array $engines, public readonly array $plugins, public readonly array $privileges, public readonly array $charsets, public readonly array $collations)
    {
    }

    /**
     * Character set descriptions used by SHOW CHARACTER SET.
     *
     * @var array<string, string>
     */
    private const CHARSETS = [
        'armscii8' => 'ARMSCII-8 Armenian',
        'ascii' => 'US ASCII',
        'big5' => 'Big5 Traditional Chinese',
        'binary' => 'Binary pseudo charset',
        'cp1250' => 'Windows Central European',
        'cp1251' => 'Windows Cyrillic',
        'cp1256' => 'Windows Arabic',
        'cp1257' => 'Windows Baltic',
        'cp850' => 'DOS West European',
        'cp852' => 'DOS Central European',
        'cp866' => 'DOS Russian',
        'cp932' => 'SJIS for Windows Japanese',
        'dec8' => 'DEC West European',
        'eucjpms' => 'UJIS for Windows Japanese',
        'euckr' => 'EUC-KR Korean',
        'gb18030' => 'China National Standard GB18030',
        'gb2312' => 'GB2312 Simplified Chinese',
        'gbk' => 'GBK Simplified Chinese',
        'geostd8' => 'GEOSTD8 Georgian',
        'greek' => 'ISO 8859-7 Greek',
        'hebrew' => 'ISO 8859-8 Hebrew',
        'hp8' => 'HP West European',
        'keybcs2' => 'DOS Kamenicky Czech-Slovak',
        'koi8r' => 'KOI8-R Relcom Russian',
        'koi8u' => 'KOI8-U Ukrainian',
        'latin1' => 'cp1252 West European',
        'latin2' => 'ISO 8859-2 Central European',
        'latin5' => 'ISO 8859-9 Turkish',
        'latin7' => 'ISO 8859-13 Baltic',
        'macce' => 'Mac Central European',
        'macroman' => 'Mac West European',
        'sjis' => 'Shift-JIS Japanese',
        'swe7' => '7bit Swedish',
        'tis620' => 'TIS620 Thai',
        'ucs2' => 'UCS-2 Unicode',
        'ujis' => 'EUC-JP Japanese',
        'utf16' => 'UTF-16 Unicode',
        'utf16le' => 'UTF-16LE Unicode',
        'utf32' => 'UTF-32 Unicode',
        'utf8mb3' => 'UTF-8 Unicode',
        'utf8mb4' => 'UTF-8 Unicode',
    ];

    /**
     * Answers the default release's implementation catalog.
     */
    public static function shared(): self
    {
        return self::$instance ??= self::of('8.4.7');
    }

    /**
     * Builds every part of the catalog for the explicitly selected release.
     */
    public static function of(string $version): self
    {
        $release = \SqlSemantics\Contract\GrammarRelease::from('mysql-' . $version);
        $tables = \MySqlMemory\System\Server\ServerTables::of($release);
        $plugins = array_map(static fn (array $plugin): array => [$plugin[0], $plugin[2], $plugin[3], $plugin[5], $plugin[9]], $tables->plugins);
        $collations = [];
        foreach (\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Catalog::shared()->collations as $collation) {
            if ($collation->availableIn($release)) {
                $collations[$collation->name] = self::sortLength($collation);
            }
        }
        ksort($collations);
        return new self($tables->engines, $plugins, PrivilegeDefinitions::of($release), self::CHARSETS, $collations);
    }

    /**
     * The legacy maximum weight expansion, or zero for variable-length UCA 9.0 weights.
     */
    public static function sortLength(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation $collation): int
    {
        $name = $collation->name;
        if ($name === 'utf8mb4_ja_0900_as_cs_ks') {
            return 24;
        }
        if ($collation->binaryOrder()) {
            return 1;
        }
        if (str_contains($name, '_0900_')) {
            return 0;
        }
        if (in_array($name, ['cp1250_czech_cs', 'gb18030_chinese_ci', 'latin1_german2_ci'], true)) {
            return 2;
        }
        if (in_array($name, ['latin2_czech_cs', 'tis620_thai_ci'], true)) {
            return 4;
        }
        return $name === 'gb18030_unicode_520_ci' || (in_array($collation->charset->name, ['ucs2', 'utf16', 'utf32', 'utf8mb3', 'utf8mb4'], true) && !str_contains($name, '_general_') && $name !== 'utf8mb3_tolower_ci') ? 8 : 1;
    }

    /**
     * Answers the name of the enabled storage engine a name or alias names, or null when none is.
     */
    public function engine(string $name): ?string
    {
        $wanted = self::ALIASES[strtolower($name)] ?? $name;
        foreach ($this->engines as [$engine, $support]) {
            if (strcasecmp($engine, $wanted) === 0) {
                return $support === 'NO' ? null : $engine;
            }
        }

        return null;
    }
}
