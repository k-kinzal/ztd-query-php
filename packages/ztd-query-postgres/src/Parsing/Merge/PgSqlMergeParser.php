<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Merge parser for PostgreSQL queries.
 */
final class PgSqlMergeParser
{
    private PgSqlCteShadowComposer $cteComposer;

    /**
     * Initializes the collaborators and state used by this merge parser.
     */
    public function __construct()
    {
        $this->cteComposer = new PgSqlCteShadowComposer();
    }

    /**
     * Parses PostgreSQL SQL into the supported structural representation.
     * @throws UnsupportedSqlException
     */
    public function parse(string $sql): PgSqlMergeStatement
    {
        $statementSql = $this->cteComposer->statementSql($sql);
        $tokens = SqlTokenStream::tokenize($statementSql, PgSqlLexerProfile::create())->significantTokens();
        $parts = new Parsing\Merge\StatementParts();
        $target = $parts->target($sql, $statementSql, $tokens);
        $join = $parts->join($sql, $statementSql, $tokens, $target['using']);
        return new PgSqlMergeStatement(
            $target['name'],
            $target['sql'],
            $target['alias'],
            $join['source'],
            $join['condition'],
            $parts->clauses($sql, $statementSql, $join['when']),
        );
    }
}
