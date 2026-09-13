<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Schema\TablePartitionKey;
use ZtdQuery\Schema\TablePartitionRelation;
use ZtdQuery\Schema\TablePartitionStrategy;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Partition parser for PostgreSQL queries.
 */
final class PgSqlPartitionParser
{
    /**
     * Reads a CREATE TABLE partition strategy and its key expressions.
     */
    public function parseKey(string $sql): ?TablePartitionKey
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $partition = (new Schema\Partition\ClauseTokens())->keywordPairIndex($tokens, 'PARTITION', 'BY');
        if ($partition === null) {
            return null;
        }

        $strategyToken = $tokens[$partition + 2] ?? null;
        if (!$strategyToken instanceof SqlToken) {
            return null;
        }
        $strategy = match (true) {
            $strategyToken->isKeyword('RANGE') => TablePartitionStrategy::Range,
            $strategyToken->isKeyword('LIST') => TablePartitionStrategy::List,
            $strategyToken->isKeyword('HASH') => TablePartitionStrategy::Hash,
            default => null,
        };
        if ($strategy === null) {
            return null;
        }

        $openIndex = $partition + 3;
        $closeIndex = (new Schema\Partition\ClauseTokens())->closingParenthesisIndex($tokens, $openIndex);
        if ($closeIndex === null) {
            return null;
        }
        $open = $tokens[$openIndex];
        $close = $tokens[$closeIndex];
        $body = substr($sql, $open->endOffset(), $close->offset - $open->endOffset());
        $expressions = SqlTokenStream::tokenize($body, PgSqlLexerProfile::create())->splitTopLevel();
        if ($expressions === [] || in_array('', $expressions, true)) {
            return null;
        }

        return new TablePartitionKey($strategy, $expressions);
    }

    /**
     * Returns the parent relation named in a PARTITION OF clause.
     */
    public function parentTable(string $sql): ?string
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $partition = (new Schema\Partition\ClauseTokens())->keywordPairIndex($tokens, 'PARTITION', 'OF');
        if ($partition === null) {
            return null;
        }

        return (new Schema\Partition\ClauseTokens())->qualifiedIdentifierAt($stream, $tokens, $partition + 2)['name'] ?? null;
    }

    /**
     * Resolves a child partition and its predicate using the parent partition key.
     */
    public function parseRelation(string $sql, TablePartitionKey $parentKey): ?TablePartitionRelation
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $partition = (new Schema\Partition\ClauseTokens())->keywordPairIndex($tokens, 'PARTITION', 'OF');
        if ($partition === null) {
            return null;
        }
        $parent = (new Schema\Partition\ClauseTokens())->qualifiedIdentifierAt($stream, $tokens, $partition + 2);
        if ($parent === null) {
            return null;
        }

        $defaultIndex = (new Schema\Partition\ClauseTokens())->keywordIndex($tokens, 'DEFAULT');
        $valuesIndex = (new Schema\Partition\ClauseTokens())->keywordPairIndex($tokens, 'FOR', 'VALUES');
        if ($defaultIndex !== null) {
            return new TablePartitionRelation($parent['name'], null);
        }
        if ($valuesIndex === null) {
            return null;
        }

        $predicate = match ($parentKey->strategy) {
            TablePartitionStrategy::Range => (new Schema\Partition\BoundPredicate())->rangePredicate($sql, $tokens, $valuesIndex + 2, $parentKey),
            TablePartitionStrategy::List => (new Schema\Partition\BoundPredicate())->listPredicate($sql, $tokens, $valuesIndex + 2, $parentKey),
            TablePartitionStrategy::Hash => null,
        };

        return $predicate === null ? null : new TablePartitionRelation($parent['name'], $predicate);
    }
}
