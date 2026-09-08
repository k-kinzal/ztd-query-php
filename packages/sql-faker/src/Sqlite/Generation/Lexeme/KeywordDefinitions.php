<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

/**
 * Explicit keyword handlers from tool/mkkeywordhash.c; spellings remain in the upstream table.
 */
final class KeywordDefinitions
{
    private const TERMINALS = [
        'ABORT', 'ACTION', 'ADD', 'AFTER', 'ALL',
        'ALTER', 'ALWAYS', 'ANALYZE', 'AND', 'AS',
        'ASC', 'ATTACH', 'AUTOINCR', 'BEFORE', 'BEGIN',
        'BETWEEN', 'BY', 'CASCADE', 'CASE', 'CAST',
        'CHECK', 'COLLATE', 'COLUMNKW', 'COMMIT', 'CONFLICT',
        'CONSTRAINT', 'CREATE', 'CTIME_KW', 'CURRENT', 'DATABASE',
        'DEFAULT', 'DEFERRABLE', 'DEFERRED', 'DELETE', 'DESC',
        'DETACH', 'DISTINCT', 'DO', 'DROP', 'EACH',
        'ELSE', 'END', 'ESCAPE', 'EXCEPT', 'EXCLUDE',
        'EXCLUSIVE', 'EXISTS', 'EXPLAIN', 'FAIL', 'FILTER',
        'FIRST', 'FOLLOWING', 'FOR', 'FOREIGN', 'FROM',
        'GENERATED', 'GROUP', 'GROUPS', 'HAVING', 'IF',
        'IGNORE', 'IMMEDIATE', 'IN', 'INDEX', 'INDEXED',
        'INITIALLY', 'INSERT', 'INSTEAD', 'INTERSECT', 'INTO',
        'IS', 'ISNULL', 'JOIN', 'JOIN_KW', 'KEY',
        'LAST', 'LIKE_KW', 'LIMIT', 'MATCH', 'MATERIALIZED',
        'NO', 'NOT', 'NOTHING', 'NOTNULL', 'NULL',
        'NULLS', 'OF', 'OFFSET', 'ON', 'OR',
        'ORDER', 'OTHERS', 'OVER', 'PARTITION', 'PLAN',
        'PRAGMA', 'PRECEDING', 'PRIMARY', 'QUERY', 'RAISE',
        'RANGE', 'RECURSIVE', 'REFERENCES', 'REINDEX', 'RELEASE',
        'RENAME', 'REPLACE', 'RESTRICT', 'RETURNING', 'ROLLBACK',
        'ROW', 'ROWS', 'SAVEPOINT', 'SELECT', 'SET',
        'TABLE', 'TEMP', 'THEN', 'TIES', 'TO',
        'TRANSACTION', 'TRIGGER', 'UNBOUNDED', 'UNION', 'UNIQUE',
        'UPDATE', 'USING', 'VACUUM', 'VALUES', 'VIEW',
        'VIRTUAL', 'WHEN', 'WHERE', 'WINDOW', 'WITH',
        'WITHIN', 'WITHOUT',
    ];

    /**
     * Binds the reviewed keyword dispatch to one exact release.
     * @param array<string, list<string>> $keywords
     */
    public function create(string $version, array $keywords): LexemeGenerator
    {
        return new VersionedLexemeGenerator($version, new VersionCase(
            ['sqlite-3.47.2'],
            new MatchingLexemeGenerator(
                static fn (LexemeInput $input): bool => in_array($input->terminal()->name, self::TERMINALS, true) && !($input->terminal()->name === 'JOIN_KW' && $input->terminal()->within('joinop')),
                new RegisteredLexemeGenerator($keywords, 'tool/mkkeywordhash.c', []),
            ),
            'sqlite-3.47.2-keywords',
        ));
    }
}
