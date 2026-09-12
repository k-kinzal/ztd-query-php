<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Replaces index-bound MATCH ... AGAINST expressions with CTE-safe relevance expressions.
 */
final class MySqlFullTextSearchRewriter
{
    /**
     * Rewrite for the supplied MySQL input.
     */
    public function rewrite(string $sql): string
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        /**
         * @var list<array{start: int, end: int, replacement: string}> $edits
         */
        $edits = [];

        foreach ($stream->significantTokens() as $token) {
            if (!$token->isKeyword('MATCH')) {
                continue;
            }
            $edit = (new Projection\FullText\ExpressionEditor())->expressionEdit($sql, $stream, $token);
            if ($edit !== null) {
                $edits[] = $edit;
            }
        }

        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr_replace($sql, $edit['replacement'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $sql;
    }

}
