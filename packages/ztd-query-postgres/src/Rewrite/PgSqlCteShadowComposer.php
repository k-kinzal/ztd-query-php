<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Rewrite;

use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlSelectRelationParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The pg sql cte shadow composer.
 */
final class PgSqlCteShadowComposer
{
    /**
     * @param PgSqlWithPrefix $prefix Reads the WITH prefix a statement may begin with
     */
    public function __construct(private readonly PgSqlWithPrefix $prefix = new PgSqlWithPrefix())
    {
    }

    /**
     * @param array<string, string> $tableCtes
     */
    public function compose(string $sql, array $tableCtes): string
    {
        $declared = array_fill_keys($this->prefix->declaredCteNames($sql), true);
        $requiredSql = [$sql];
        $requiredCtes = [];
        foreach (array_reverse($tableCtes, true) as $table => $cte) {
            $normalized = strtolower($table);
            if (isset($declared[$normalized])) {
                continue;
            }
            $referenced = false;
            foreach ($requiredSql as $requiredPart) {
                if ($this->prefix->referencesIdentifier($requiredPart, $table)) {
                    $referenced = true;
                }
            }
            if (!$referenced) {
                continue;
            }
            $requiredCtes[$table] = $cte;
            $requiredSql[] = $cte;
        }

        $requiredCtes = array_reverse($requiredCtes, true);
        $ctes = $requiredCtes;
        $shadowedTables = array_keys($requiredCtes);

        if ($ctes === []) {
            return $sql;
        }

        $sql = (new PgSqlSelectRelationParser())->unqualify($sql, $shadowedTables);
        $tokens = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->significantTokens();
        $with = $tokens[0] ?? null;
        if ($with === null || !$with->isKeyword('WITH')) {
            return 'WITH ' . implode(",\n", $ctes) . "\n" . $sql;
        }

        $insertionToken = $with;
        $next = $tokens[1] ?? null;
        if ($next !== null && $next->isTopLevel() && $next->isKeyword('RECURSIVE')) {
            $insertionToken = $next;
        }

        return substr_replace(
            $sql,
            ' ' . implode(",\n", $ctes) . ",\n",
            $insertionToken->endOffset(),
            0,
        );
    }

}
