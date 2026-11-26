<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Index;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Rules\Definition\KeyTerms;
use SqlSemantics\Platform\Sqlite\Rules\Definition\PrimaryKeyRule;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;

/**
 * Derives SQLite's contiguous automatic index identities in an ordinary table declaration.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ConstraintIndexes
{
    /**
     * Records distinct native key signatures and the ordinal of WITHOUT ROWID primary storage.
     * Returns null when a key term lacks a checked column coordinate.
     */
    public function layout(CreateTable $table): ?ConstraintIndexLayout
    {
        $names = Comparison::AsciiInsensitive;
        $primary = (new PrimaryKeyRule())->key($table, (new TableDeclaration())->domains($table), $names);
        $alias = $table->withoutRowid() ? null : $primary->rowid;
        $collations = [];
        $slots = [];
        foreach ($table->columns as $slot => $column) {
            $slots[$names->fold($column->name->value)] = $slot;
            $collations[] = $names->fold($column->collation()->value ?? 'binary');
        }
        $keys = $this->inlineKeys($table, $collations, $alias);
        $column = $primary->columns[0] ?? null;
        $storage = $table->withoutRowid() && $column !== null ? $this->signature([[$column, $collations[$column]]]) : null;
        foreach ($table->constraints as $run) {
            foreach ($run->items as $constraint) {
                if (!$constraint instanceof TablePrimaryKey && !$constraint instanceof TableUnique) {
                    continue;
                }
                if ($constraint instanceof TablePrimaryKey && $alias !== null) {
                    continue;
                }
                $terms = [];
                foreach ($constraint->terms as $term) {
                    $key = $this->term($term->expression, $slots, $collations);
                    if ($key === null) {
                        return null;
                    }
                    $terms[] = $key;
                }
                $keys[$this->signature($terms)] = true;
                if ($table->withoutRowid() && $constraint instanceof TablePrimaryKey) {
                    $storage = $this->signature($terms);
                }
            }
        }

        return $this->storageLayout(array_keys($keys), $table->withoutRowid(), $storage);
    }

    /**
     * Finds the primary key's first registered signature in the physical index sequence.
     * @param list<string> $signatures
     */
    public function storageLayout(array $signatures, bool $withoutRowid, ?string $primary): ?ConstraintIndexLayout
    {
        if (!$withoutRowid) {
            return new ConstraintIndexLayout(count($signatures));
        }
        $position = array_search($primary, $signatures, true);

        return $position === false ? null : new ConstraintIndexLayout(count($signatures), $position + 1);
    }

    /**
     * Records inline UNIQUE and indexed PRIMARY KEY signatures using the final column collation.
     *
     * @param list<string> $collations
     * @return array<string, true>
     */
    public function inlineKeys(CreateTable $table, array $collations, ?int $alias): array
    {
        $keys = [];
        foreach ($table->columns as $slot => $column) {
            foreach ($column->constraints as $constraint) {
                if ($constraint instanceof ColumnUnique || ($constraint instanceof ColumnPrimaryKey && $slot !== $alias)) {
                    $keys[$this->signature([[$slot, $collations[$slot]]])] = true;
                }
            }
        }

        return $keys;
    }

    /**
     * Resolves one key slot and its outermost collation.
     *
     * @param array<string|int, int> $slots
     * @param list<string> $collations
     * @return array{int, string}|null
     */
    public function term(Scalar $expression, array $slots, array $collations): ?array
    {
        $names = Comparison::AsciiInsensitive;
        $name = (new KeyTerms())->column($expression);
        $slot = $name === null ? null : ($slots[$names->fold($name->value)] ?? null);
        if ($slot === null) {
            return null;
        }
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return [$slot, $expression instanceof Collate ? $names->fold($expression->collation->value) : $collations[$slot]];
    }

    /**
     * Encodes ordered slot and collation identities without delimiter collisions.
     *
     * @param list<array{int, string}> $terms
     */
    public function signature(array $terms): string
    {
        $signature = '';
        foreach ($terms as [$slot, $collation]) {
            $signature .= $slot . ':' . strlen($collation) . ':' . $collation;
        }

        return $signature;
    }
}
