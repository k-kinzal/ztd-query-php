<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parse;

use PhpMyAdmin\SqlParser\Components\PartitionDefinition;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Schema\Partition\TablePartitioning;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Converts MySQL RANGE and LIST partition metadata into row predicates.
 */
final class MySqlPartitioningParser
{
    /**
     * Reads.
     *
     * @param CreateStatement $statement
     * @return ?TablePartitioning
     */
    public function parse(CreateStatement $statement): ?TablePartitioning
    {
        if ($statement->partitionBy === null) {
            return null;
        }

        $partitionBy = $this->partitionExpression($statement->partitionBy);
        if ($partitionBy === null || !is_array($statement->partitions)) {
            return new TablePartitioning([]);
        }

        [$kind, $expression] = $partitionBy;

        return new TablePartitioning(match ($kind) {
            'RANGE' => $this->rangePredicates($expression, $statement->partitions),
            'LIST' => $this->listPredicates($expression, $statement->partitions),
        });
    }

    /**
     * Answers what a PARTITION BY divides a table on, and how.
     *
     * @param string $partitionBy The clause, as written
     *
     * @return array{'RANGE'|'LIST', non-empty-string}|null How it divides and what it divides on, or null where ZTD cannot simulate the division
     */
    public function partitionExpression(string $partitionBy): ?array
    {
        $tokens = SqlTokenStream::tokenize($partitionBy, MySqlLexerProfile::create())->significantTokens();
        $kind = $tokens[0] ?? null;
        if (!$kind instanceof SqlToken) {
            return null;
        }
        $partitionKind = $kind->isKeyword('RANGE')
            ? 'RANGE'
            : ($kind->isKeyword('LIST') ? 'LIST' : null);
        if ($partitionKind === null) {
            return null;
        }

        $open = $tokens[1] ?? null;
        $close = $tokens[count($tokens) - 1] ?? null;
        if (!$open instanceof SqlToken) {
            return null;
        }
        if (!$close instanceof SqlToken) {
            return null;
        }
        if (!$this->isSymbol($open, '(') || !$this->isSymbol($close, ')')) {
            return null;
        }

        $expression = substr(
            $partitionBy,
            $open->endOffset(),
            $close->offset - $open->endOffset(),
        );
        if ($expression === '') {
            return null;
        }

        return [$partitionKind, $expression];
    }

    /**
     * Answers which rows land in each partition of a table divided by range.
     *
     * A range partition names only its upper bound; its lower one is the
     * partition before it. The first partition has no lower bound, so a row
     * whose expression is null lands there, which is where MySQL puts it.
     *
     * @param string $expression What the table is divided on
     * @param array<int|string, PartitionDefinition> $partitions The partitions, in the order they were declared
     *
     * @return array<string, string> Partition name => the predicate its rows satisfy, or none where ZTD cannot say
     */
    public function rangePredicates(string $expression, array $partitions): array
    {
        $predicates = [];
        $lowerBound = null;
        foreach ($partitions as $partition) {
            $upperBound = $this->partitionValue($partition, 'LESS THAN');
            if ($upperBound === null) {
                return [];
            }

            $partitionExpression = "($expression)";
            $isMaximum = strcasecmp($upperBound, 'MAXVALUE') === 0;
            if ($lowerBound === null) {
                $predicates[$partition->name] = $isMaximum
                    ? 'TRUE'
                    : "$partitionExpression IS NULL OR $partitionExpression < $upperBound";
            } else {
                $predicates[$partition->name] = $isMaximum
                    ? "$partitionExpression >= $lowerBound"
                    : "$partitionExpression >= $lowerBound AND $partitionExpression < $upperBound";
            }
            $lowerBound = $upperBound;
        }

        return $predicates;
    }

    /**
     * Answers which rows land in each partition of a table divided by value.
     *
     * Null is a value a list partition may name, and no comparison matches
     * null, so a partition naming it needs a test written for it.
     *
     * @param string $expression What the table is divided on
     * @param array<int|string, PartitionDefinition> $partitions The partitions, as they were declared
     *
     * @return array<string, string> Partition name => the predicate its rows satisfy, or none where ZTD cannot say
     */
    public function listPredicates(string $expression, array $partitions): array
    {
        $predicates = [];
        foreach ($partitions as $partition) {
            $values = $this->partitionValue($partition, 'IN');
            if ($values === null) {
                return [];
            }

            $parts = SqlTokenStream::tokenize($values, MySqlLexerProfile::create())->splitTopLevel();
            $nonNull = [];
            $hasNull = false;
            foreach ($parts as $part) {
                $partTokens = SqlTokenStream::tokenize($part, MySqlLexerProfile::create())->significantTokens();
                if (count($partTokens) === 1 && $partTokens[0]->isKeyword('NULL')) {
                    $hasNull = true;
                    continue;
                }
                $nonNull[] = $part;
            }

            $partitionExpression = "($expression)";
            $predicate = $nonNull === [] ? '' : $partitionExpression . ' IN (' . implode(', ', $nonNull) . ')';
            if ($hasNull) {
                $nullPredicate = "$partitionExpression IS NULL";
                $predicate = $predicate === '' ? $nullPredicate : "($predicate OR $nullPredicate)";
            }
            if ($predicate === '') {
                return [];
            }
            $predicates[$partition->name] = $predicate;
        }

        return $predicates;
    }

    /**
     * Answers the values a partition was declared to hold.
     *
     * @param PartitionDefinition $partition Partition to read
     * @param string $expectedType How the table divides, which the partition must be declared for
     *
     * @return string|null The values, as written, or null where the partition divides some other way
     */
    public function partitionValue(PartitionDefinition $partition, string $expectedType): ?string
    {
        if (strcasecmp($partition->type, $expectedType) !== 0) {
            return null;
        }
        if (is_string($partition->expr)) {
            return $partition->expr;
        }
        $raw = $partition->expr->expr;

        return is_string($raw) ? substr($raw, 1, -1) : null;
    }

    /**
     * Reports whether a token is this symbol.
     *
     * @param SqlToken $token Token to test
     * @param string $symbol Symbol it must be
     *
     * @return bool True when it is
     */
    public function isSymbol(SqlToken $token, string $symbol): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
