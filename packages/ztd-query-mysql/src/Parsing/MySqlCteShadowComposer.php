<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the My Sql Cte Shadow Composer contract for MySQL.
 */
final class MySqlCteShadowComposer
{
    private readonly SqlLexerProfile $lexerProfile;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct()
    {
        $this->lexerProfile = MySqlLexerProfile::create();
    }

    /**
     * @param array<string, string> $tableCtes
     */
    public function compose(string $sql, array $tableCtes): string
    {
        $declared = array_fill_keys($this->declaredCteNames($sql), true);
        $requiredSql = [$sql];
        $requiredCtes = [];
        foreach (array_reverse($tableCtes, true) as $table => $cte) {
            $normalized = strtolower($table);
            if (isset($declared[$normalized])) {
                continue;
            }
            $referenced = false;
            foreach ($requiredSql as $requiredPart) {
                if ((new Parsing\Cte\IdentifierReferences($this->lexerProfile))->referencesIdentifier($requiredPart, $table)) {
                    $referenced = true;
                }
            }
            if (!$referenced) {
                continue;
            }
            $requiredCtes[$table] = $cte;
            $requiredSql[] = $cte;
        }

        $ctes = array_reverse($requiredCtes, true);
        $shadowedTables = array_keys($ctes);

        if ($ctes === []) {
            return $sql;
        }

        $sql = (new MySqlSelectRelationParser())->unqualify($sql, $shadowedTables);
        $tokens = SqlTokenStream::tokenize($sql, $this->lexerProfile)->significantTokens();
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

    /**
     * @return list<string>
     */
    public function declaredCteNames(string $sql): array
    {
        return (new Parsing\Cte\HeaderParser($this->lexerProfile))->parseHeader($sql)['names'];
    }

    /**
     * Carry Prefix for the supplied MySQL input.
     */
    public function carryPrefix(string $originalSql, string $rewrittenStatement): string
    {
        $header = (new Parsing\Cte\HeaderParser($this->lexerProfile))->parseHeader($originalSql);
        if ($header['statementOffset'] === null) {
            return $rewrittenStatement;
        }

        $prefix = rtrim(substr($originalSql, 0, $header['statementOffset']));

        $rewrittenTokens = SqlTokenStream::tokenize($rewrittenStatement, $this->lexerProfile)->significantTokens();
        $rewrittenWith = $rewrittenTokens[0] ?? null;
        if ($rewrittenWith !== null && $rewrittenWith->isKeyword('WITH')) {
            $rewrittenHeader = (new Parsing\Cte\HeaderParser($this->lexerProfile))->parseHeader($rewrittenStatement);
            $rewrittenStatementOffset = $rewrittenHeader['statementOffset'];
            if ($rewrittenStatementOffset === null) {
                return $prefix . "\n" . $rewrittenStatement;
            }

            $headerParser = new Parsing\Cte\HeaderParser($this->lexerProfile);
            $rewrittenBody = $headerParser->contents($rewrittenStatement, $rewrittenStatementOffset)['body'];
            $rewrittenTail = substr($rewrittenStatement, $rewrittenStatementOffset);
            if ((new Parsing\Cte\IdentifierReferences($this->lexerProfile))->referencesAnyIdentifier($rewrittenBody, $header['names'])) {
                return $prefix . ",\n" . $rewrittenBody . "\n" . $rewrittenTail;
            }

            $original = $headerParser->contents($originalSql, $header['statementOffset']);
            return $original['leading']
                . 'WITH '
                . ($original['recursive'] ? 'RECURSIVE ' : '')
                . $rewrittenBody
                . ",\n"
                . $original['body']
                . "\n"
                . $rewrittenTail;
        }

        return $prefix . "\n" . $rewrittenStatement;
    }

    /**
     * Statement Sql for the supplied MySQL input.
     */
    public function statementSql(string $sql): string
    {
        $offset = (new Parsing\Cte\HeaderParser($this->lexerProfile))->parseHeader($sql)['statementOffset'];

        return $offset === null ? $sql : substr($sql, $offset);
    }

}
