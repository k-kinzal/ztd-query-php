<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Source;

use RuntimeException;

/**
 * Extracts the keyword table from SQLite's `tool/mkkeywordhash.c`.
 *
 * Each keyword names the features it belongs to, and a build leaves out a
 * keyword none of whose features are compiled in. The features follow the
 * same `SQLITE_OMIT_*` and `SQLITE_ENABLE_*` names the grammar's own
 * conditionals use, so one list of defines decides both.
 *
 * @visibility root
 */
final class KeywordHash
{
    /**
     * Features that are compiled in unless the named option omits them.
     */
    public const OMITTABLE = [
        'ALTER' => ['SQLITE_OMIT_ALTERTABLE', 'SQLITE_OMIT_VIRTUALTABLE'],
        'ANALYZE' => ['SQLITE_OMIT_ANALYZE'],
        'ATTACH' => ['SQLITE_OMIT_ATTACH'],
        'AUTOINCR' => ['SQLITE_OMIT_AUTOINCREMENT'],
        'CAST' => ['SQLITE_OMIT_CAST'],
        'COMPOUND' => ['SQLITE_OMIT_COMPOUND_SELECT'],
        'CONFLICT' => ['SQLITE_OMIT_CONFLICT_CLAUSE'],
        'EXPLAIN' => ['SQLITE_OMIT_EXPLAIN'],
        'FKEY' => ['SQLITE_OMIT_FOREIGN_KEY'],
        'PRAGMA' => ['SQLITE_OMIT_PRAGMA'],
        'REINDEX' => ['SQLITE_OMIT_REINDEX'],
        'SUBQUERY' => ['SQLITE_OMIT_SUBQUERY'],
        'TRIGGER' => ['SQLITE_OMIT_TRIGGER'],
        'VIEW' => ['SQLITE_OMIT_VIEW'],
        'VTAB' => ['SQLITE_OMIT_VIRTUALTABLE'],
        'AUTOVACUUM' => ['SQLITE_OMIT_AUTOVACUUM'],
        'CTE' => ['SQLITE_OMIT_CTE'],
        'UPSERT' => ['SQLITE_OMIT_UPSERT'],
        'WINDOWFUNC' => ['SQLITE_OMIT_WINDOWFUNC'],
        'GENCOL' => ['SQLITE_OMIT_GENERATED_COLUMNS'],
        'RETURNING' => ['SQLITE_OMIT_RETURNING'],
    ];

    /**
     * Features that are compiled in only when the named option enables them.
     */
    public const ENABLEABLE = [
        'ORDERSET' => 'SQLITE_ENABLE_ORDERED_SET_AGGREGATES',
    ];

    /**
     * @param list<string> $defines Names defined for the build, as `-D` would pass them
     */
    public function __construct(private readonly array $defines = [])
    {
    }

    /**
     * Reads the keyword table of the build.
     *
     * @param string $source Contents of `mkkeywordhash.c`
     *
     * @return array{keywords: array<string, string>} Terminal name by upper-cased keyword
     *
     * @throws RuntimeException When the file declares no keyword
     */
    public function parse(string $source): array
    {
        if (preg_match_all('/\{\s*"([A-Z_]+)"\s*,\s*"TK_([A-Z_]+)"\s*,\s*([A-Z_|\s]+?)\s*,\s*[0-9]+\s*\}/', $source, $matches, PREG_SET_ORDER) === 0) {
            throw new RuntimeException('No keyword found in mkkeywordhash.c');
        }
        $keywords = [];
        foreach ($matches as $match) {
            $features = preg_split('/\s*\|\s*/', trim($match[3]));
            if ($features !== false && $this->compiledIn($features)) {
                $keywords[$match[1]] = $match[2];
            }
        }
        ksort($keywords);

        return ['keywords' => $keywords];
    }

    /**
     * Reports whether any of a keyword's features is compiled in.
     *
     * @param list<string> $features Feature names the keyword belongs to
     *
     * @return bool True when at least one feature is compiled in
     */
    public function compiledIn(array $features): bool
    {
        foreach ($features as $feature) {
            if ($feature === 'ALWAYS') {
                return true;
            }
            $enable = self::ENABLEABLE[$feature] ?? null;
            if ($enable !== null) {
                if (in_array($enable, $this->defines, true)) {
                    return true;
                }
                continue;
            }
            $omitted = false;
            foreach (self::OMITTABLE[$feature] ?? [] as $omit) {
                $omitted = $omitted || in_array($omit, $this->defines, true);
            }
            if (!$omitted) {
                return true;
            }
        }

        return false;
    }
}
