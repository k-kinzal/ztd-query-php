<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Connection\StatementInterface;

/**
 * Relational candidate keys used to detect INSERT conflicts.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class CandidateKeySet
{
    /**
     * @param array<string, array<int, string>> $keys Columns of each candidate key, under the key's name
     * @param CandidateKeyMatch $values Reads what a row carries in a key
     */
    public function __construct(
        private readonly array $keys,
        private readonly CandidateKeyMatch $values = new CandidateKeyMatch(),
    ) {
    }

    /**
     * @param array<int, string> $primaryKey
     * @param array<string, array<int, string>> $uniqueConstraints
     */
    public static function fromSchema(array $primaryKey, array $uniqueConstraints = []): self
    {
        $keys = $uniqueConstraints;
        if ($primaryKey !== []) {
            $keys = ['PRIMARY' => $primaryKey] + $keys;
        }

        return new self($keys);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * Answers the first candidate key an incoming row would collide on.
     *
     * The rows searched may be keyed, and the conflict reports the key it was
     * found at: a caller that has narrowed the rows it is willing to collide
     * with still has to say which of its own rows that was.
     *
     * @param Row $row Row that would be written
     * @param array<int, Row> $existingRows Rows it could collide with, under the keys the caller knows them by
     *
     * @return CandidateKeyConflict|null Where and on which key it collides, or null when it does not
     */
    public function findConflict(array $row, array $existingRows): ?CandidateKeyConflict
    {
        foreach ($this->keys as $keyName => $columns) {
            $values = $this->values->of($row, $columns);
            if ($values === null) {
                continue;
            }

            foreach ($existingRows as $rowIndex => $existingRow) {
                if ($this->values->carriedBy($values, $existingRow)) {
                    return new CandidateKeyConflict($rowIndex, $keyName, $values);
                }
            }
        }

        return null;
    }
}
