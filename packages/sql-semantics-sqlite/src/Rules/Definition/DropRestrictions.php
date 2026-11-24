<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\ColumnRemoval;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Facts;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Collation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NullAllowed;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\Deferrability;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ForeignKeyClause;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ForeignKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;

/**
 * Derives removal restrictions using the bound declaration and its local members.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DropRestrictions
{
    /**
     * Records the parts of the parsed table definition needed to resolve a later removal.
     */
    public function attach(CreateTable $definition, Facts $facts, Table $table): Table
    {
        $protected = $this->protected($definition, $facts, $table);
        $removal = $protected === null ? null : new ColumnRemoval(count($table->columns), $protected);

        return new Table($table->name, $table->profile, $table->columns, $table->implicit, $table->complete, $table->kind, $removal);
    }

    /**
     * Finds the declared slots used by keys and surviving expressions.
     *
     * @return list<int>|null Null when a definition role has no removal rule
     */
    public function protected(CreateTable $definition, Facts $facts, Table $table): ?array
    {
        $slots = [];
        $names = [];
        foreach ($table->columns as $slot => $column) {
            $slots[spl_object_id($column)] = $slot;
            $names[Comparison::AsciiInsensitive->fold($column->name->value)] = $slot;
        }
        $protected = [];
        foreach ($definition->columns as $slot => $column) {
            foreach ($column->constraints as $constraint) {
                $blocked = $this->inline($constraint, $facts, $slots, $slot, $table->implicit !== []);
                if ($blocked === null) {
                    return null;
                }
                array_push($protected, ...$blocked);
            }
        }
        foreach ($definition->constraints as $run) {
            foreach ($run->items as $constraint) {
                $blocked = $this->table($constraint, $facts, $slots, $names, $table->implicit !== []);
                if ($blocked === null) {
                    return null;
                }
                array_push($protected, ...$blocked);
            }
        }

        return $protected;
    }

    /**
     * Finds restrictions contributed by a constraint that disappears with its owning column.
     *
     * @param array<int, int> $slots The declared column objects by identity
     * @return list<int>|null
     */
    public function inline(ColumnConstraint $constraint, Facts $facts, array $slots, int $owner, bool $rowid): ?array
    {
        return match (true) {
            $constraint instanceof ColumnPrimaryKey, $constraint instanceof ColumnUnique => [$owner],
            $constraint instanceof ColumnCheck => $this->uses($constraint->expression, $facts, $slots, $owner, $rowid),
            $constraint instanceof Generated => $this->uses($constraint->expression, $facts, $slots, $owner, false),
            $constraint instanceof Collation, $constraint instanceof ConstraintName, $constraint instanceof DefaultExpression,
            $constraint instanceof DefaultLiteral, $constraint instanceof DefaultWord, $constraint instanceof NotNull,
            $constraint instanceof NullAllowed, $constraint instanceof ForeignKeyClause, $constraint instanceof Deferrability => [],
            default => null,
        };
    }

    /**
     * Finds restrictions contributed by a constraint that survives a column removal.
     *
     * @param array<int, int> $slots The declared column objects by identity
     * @param array<int|string, int> $names The declared slots by folded name
     * @return list<int>|null
     */
    public function table(TableConstraint $constraint, Facts $facts, array $slots, array $names, bool $rowid): ?array
    {
        if ($constraint instanceof TableCheck) {
            return $this->uses($constraint->expression, $facts, $slots, null, $rowid);
        }
        $protected = [];
        if ($constraint instanceof TablePrimaryKey || $constraint instanceof TableUnique) {
            foreach ($constraint->terms as $term) {
                array_push($protected, ...$this->uses($term->expression, $facts, $slots, null, false));
            }

            return $protected;
        }
        if ($constraint instanceof ForeignKey) {
            foreach ($constraint->columns as $column) {
                $slot = $names[Comparison::AsciiInsensitive->fold($column->name->value)] ?? null;
                if ($slot !== null) {
                    $protected[] = $slot;
                }
            }

            return $protected;
        }

        return $constraint instanceof ConstraintName ? [] : null;
    }

    /**
     * Follows resolved column references in a surviving expression.
     *
     * @param array<int, int> $slots The declared column objects by identity
     * @return list<int>
     */
    public function uses(Scalar $expression, Facts $facts, array $slots, ?int $owner, bool $allowsRowid): array
    {
        $protected = [];
        foreach ((new Limits())->nodes($expression, true) as $node) {
            if (!$node instanceof Scalar || !$facts->covers($node)) {
                continue;
            }
            $resolution = $facts->scalar($node)->resolution;
            $column = $resolution instanceof ResolvedColumn ? $resolution->declaration() : null;
            if ($column === null) {
                continue;
            }
            $slot = $slots[spl_object_id($column)] ?? null;
            if ($slot === null || $slot === $owner) {
                continue;
            }
            if ($allowsRowid && in_array(Comparison::AsciiInsensitive->fold($column->name->value), ['rowid', '_rowid_', 'oid'], true)) {
                continue;
            }
            $protected[] = $slot;
        }

        return $protected;
    }
}
