<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateColumnLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;

/**
 * Resolves inline and table-level keys to their checked roles.
 *
 * @visibility SqlSemantics
 */
final class KeyConstraintLowering
{
    /**
     * Extracts the ordered index constraints from one column and preserves the other qualifiers.
     * @return array{ColumnDefinition, list<PrimaryKey|UniqueKey>}
     * @throws InvalidConstruction When a constraint has unsupported index options
     */
    public function column(ColumnDefinition $column): array
    {
        $qualifiers = [];
        $keys = [];
        $pending = null;
        $attributes = [];
        $reference = new Identifier($column->name->value);
        foreach ($column->qualifiers as $qualifier) {
            if ($pending !== null && $qualifier instanceof ConstraintAttribute) {
                $attributes[] = $qualifier;
                continue;
            }
            if ($pending !== null) {
                $keys[] = $this->key($pending, [$reference], [], $attributes);
                $pending = null;
                $attributes = [];
            }
            if ($qualifier instanceof ColumnPrimaryKey || $qualifier instanceof ColumnUnique) {
                $pending = $qualifier;
            } else {
                $qualifiers[] = $qualifier;
            }
        }
        if ($pending !== null) {
            $keys[] = $this->key($pending, [$reference], [], $attributes);
        }
        if ($keys === []) {
            return [$column, []];
        }
        $remaining = new ColumnDefinition($column->name, $column->type, $column->storage, $column->compression, $column->options, $qualifiers);

        return [$remaining, $keys];
    }

    /**
     * Resolves a table-level index constraint's ordered key and included references.
     */
    public function table(TablePrimaryKey|TableUnique $key): PrimaryKey|UniqueKey
    {
        return $this->key($key, $this->references($key->columns), $this->references($key->included), $key->attributes);
    }

    /**
     * Turns decoded AST names into detached local references.
     * @param list<Name> $columns
     * @return list<Identifier>
     */
    public function references(array $columns): array
    {
        $references = [];
        foreach ($columns as $column) {
            $references[] = new Identifier($column->value);
        }

        return $references;
    }

    /**
     * Closes the selected constraint role and its policies.
     * @param list<Identifier> $keys
     * @param list<Identifier> $included
     * @param list<ConstraintAttribute> $attributes
     * @throws InvalidConstruction When the key has unsupported index options
     */
    public function key(ColumnPrimaryKey|ColumnUnique|TablePrimaryKey|TableUnique $constraint, array $keys, array $included, array $attributes): PrimaryKey|UniqueKey
    {
        Check::input($constraint->options === [] && $constraint->tablespace === null, 'A table key requires default index parameters.');
        $name = $constraint->name === null ? null : new Identifier($constraint->name->value);
        $timing = $this->timing($attributes);
        if ($constraint instanceof ColumnUnique || $constraint instanceof TableUnique) {
            return new UniqueKey($keys, $included, $name, $timing, $constraint->nullsDistinct === false);
        }

        return new PrimaryKey($keys, $included, $name, $timing);
    }

    /**
     * Resolves written constraint attributes to the effective three-way timing.
     * @param list<ConstraintAttribute> $attributes
     * @throws InvalidConstruction When the attributes are repeated or incompatible
     */
    public function timing(array $attributes): KeyTiming
    {
        $deferrable = null;
        $deferred = null;
        foreach ($attributes as $attribute) {
            switch ($attribute) {
                case ConstraintAttribute::Deferrable:
                case ConstraintAttribute::NotDeferrable:
                    Check::input($deferrable === null, 'A table key can specify deferrability only once.');
                    $deferrable = $attribute === ConstraintAttribute::Deferrable;
                    break;
                case ConstraintAttribute::InitiallyImmediate:
                case ConstraintAttribute::InitiallyDeferred:
                    Check::input($deferred === null, 'A table key can specify initial timing only once.');
                    $deferred = $attribute === ConstraintAttribute::InitiallyDeferred;
                    break;
                case ConstraintAttribute::NotValid:
                case ConstraintAttribute::NoInherit:
                    throw new InvalidConstruction('The attribute is outside the table key definition.');
            }
        }
        Check::input($deferrable !== false || $deferred !== true, 'A not-deferrable table key cannot initially be deferred.');

        return match (true) {
            $deferred === true => KeyTiming::InitiallyDeferred,
            $deferrable === true => KeyTiming::InitiallyImmediate,
            default => KeyTiming::NotDeferrable,
        };
    }

    /**
     * Collects checked fields and separate index-creating constraints in native declaration order.
     * @return array{list<TableColumn>, ?PrimaryKey, list<UniqueKey>}
     * @throws InvalidConstruction When the definition cannot select a checked table role
     */
    public function definitions(BoundStatement $operation, ListedColumns $definition): array
    {
        $columns = [];
        $lowering = new CreateColumnLowering();
        $primary = null;
        $unique = [];
        foreach ($definition->elements as $element) {
            if ($element instanceof TablePrimaryKey || $element instanceof TableUnique) {
                $constraints = [$this->table($element)];
            } else {
                Check::input($element instanceof ColumnDefinition, 'CREATE TABLE requires ordinary column definitions.');
                [$column, $constraints] = $this->column($element);
                $columns[] = $lowering->lower($operation, $column);
            }
            foreach ($constraints as $constraint) {
                if ($constraint instanceof PrimaryKey) {
                    Check::input($primary === null, 'CREATE TABLE can declare only one primary key.');
                    $primary = $constraint;
                } else {
                    $unique[] = $constraint;
                }
            }
        }

        return [$columns, $primary, $unique];
    }
}
