<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Components\PartitionDefinition;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Schema\TablePartitioning;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Converts MySQL RANGE and LIST partition metadata into row predicates.
 */
final class MySqlPartitioningParser
{
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

    /** @return array{'RANGE'|'LIST', non-empty-string}|null */
    private function partitionExpression(string $partitionBy): ?array
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
     * @param array<int|string, PartitionDefinition> $partitions
     * @return array<string, string>
     */
    private function rangePredicates(string $expression, array $partitions): array
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
     * @param array<int|string, PartitionDefinition> $partitions
     * @return array<string, string>
     */
    private function listPredicates(string $expression, array $partitions): array
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

    private function partitionValue(PartitionDefinition $partition, string $expectedType): ?string
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

    private function isSymbol(SqlToken $token, string $symbol): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
