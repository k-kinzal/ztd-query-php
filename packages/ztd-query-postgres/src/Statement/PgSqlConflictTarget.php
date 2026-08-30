<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Statement;

use InvalidArgumentException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Schema\PartialUniqueIndex;

/**
 * Structured ON CONFLICT arbiter target.
 */
final class PgSqlConflictTarget
{
    /**
     * @param array<int, string> $columns
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly bool $specified,
        public readonly array $columns = [],
        public readonly ?string $predicate = null,
        public readonly ?string $constraint = null,
    ) {
        if ($constraint !== null && $columns !== []) {
            throw new InvalidArgumentException('A conflict target cannot use columns and a constraint together.');
        }
    }

    /**
     * @param array<string, PartialUniqueIndex> $partialIndexes
     * @return array{keys: CandidateKeySet, predicate: string|null}
     *
     * @throws UnsupportedSqlException
     */
    public function resolve(CandidateKeySet $candidateKeys, array $partialIndexes, string $sql): array
    {
        if (!$this->specified) {
            return ['keys' => $candidateKeys, 'predicate' => null];
        }

        if ($this->constraint !== null) {
            foreach ($candidateKeys->keys() as $name => $columns) {
                if (strcasecmp($name, $this->constraint) === 0) {
                    return ['keys' => new CandidateKeySet([$name => $columns]), 'predicate' => null];
                }
            }

            throw new UnsupportedSqlException($sql, 'Cannot resolve ON CONFLICT constraint');
        }

        $targetColumns = self::normalizedColumns($this->columns);
        $fullMatches = [];
        foreach ($candidateKeys->keys() as $name => $columns) {
            if (self::normalizedColumns($columns) === $targetColumns) {
                $fullMatches[$name] = $columns;
            }
        }
        if ($fullMatches !== []) {
            return ['keys' => new CandidateKeySet($fullMatches), 'predicate' => null];
        }

        $partialMatches = [];
        foreach ($partialIndexes as $name => $index) {
            if (self::normalizedColumns($index->columns) === $targetColumns) {
                $partialMatches[$name] = $index->columns;
            }
        }
        if ($this->predicate === null || count($partialMatches) !== 1) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve ON CONFLICT partial index');
        }

        return ['keys' => new CandidateKeySet($partialMatches), 'predicate' => $this->predicate];
    }

    /**
     * Answers the target's columns as the table knows them.
     *
     * @param array<int, string> $columns Columns to read
     *
     * @return list<string> What it answers
     */
    public static function normalizedColumns(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            $normalized[] = strtolower($column);
        }
        sort($normalized);

        return $normalized;
    }
}
