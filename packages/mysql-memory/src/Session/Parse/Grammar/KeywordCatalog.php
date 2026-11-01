<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Grammar;

use RuntimeException;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\SyntaxException;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Derives INFORMATION_SCHEMA.KEYWORDS from the selected lexer and identifier grammar.
 *
 * Conditional grammar features can reject an identifier even though the server's keyword
 * catalog reports it as nonreserved. Those release changes are explicit below.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-keywords-table.html.
 *
 * @visibility MySqlMemory
 */
final class KeywordCatalog
{
    /** @var array<string, list<array{string, int}>> */
    private static array $releases = [];

    /**
     * @throws RuntimeException When the release is not MySQL
     * @return list<array{string, int}>
     */
    public function of(GrammarRelease $release): array
    {
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            return [];
        }
        if (isset(self::$releases[$release->value])) {
            return self::$releases[$release->value];
        }
        $tables = require (new VersionRegistry())->resolve('mysql', $release->value)->keywordPath;
        $keywords = [];
        foreach (is_array($tables) && isset($tables['keywords']) && is_array($tables['keywords']) ? array_keys($tables['keywords']) : [] as $word) {
            if (is_string($word) && preg_match('/\A[A-Z][A-Z0-9_]*\z/', $word) === 1) {
                $keywords[] = $word;
            }
        }
        sort($keywords, SORT_STRING);
        $exceptions = match ($release) {
            GrammarRelease::MySql8044, GrammarRelease::MySql810 => [],
            GrammarRelease::MySql820 => ['PARALLEL'],
            GrammarRelease::MySql830 => ['CUBE', 'PARALLEL', 'QUALIFY'],
            GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => ['CUBE', 'MANUAL', 'PARALLEL', 'QUALIFY', 'TABLESAMPLE'],
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => throw new RuntimeException('A MySQL release is required.'),
        };
        $parser = new MySqlParser($release->value);
        $rows = [];
        foreach ($keywords as $word) {
            try {
                $parser->parse('SELECT 1 AS ' . $word);
                $reserved = 0;
            } catch (SyntaxException) {
                $reserved = in_array($word, $exceptions, true) ? 0 : 1;
            }
            $rows[] = [$word, $reserved];
        }
        self::$releases[$release->value] = $rows;
        return $rows;
    }
}
