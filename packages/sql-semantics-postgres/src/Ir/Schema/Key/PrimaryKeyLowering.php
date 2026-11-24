<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;

/**
 * Resolves the two written forms of a native primary key to one checked local reference role.
 *
 * @visibility SqlSemantics
 */
final class PrimaryKeyLowering
{
    /**
     * Extracts the column key and the attributes written after it.
     * @return array{ColumnDefinition, ?PrimaryKey}
     * @throws InvalidConstruction When the column has multiple keys or unsupported index options
     */
    public function column(ColumnDefinition $column): array
    {
        $qualifiers = [];
        $key = null;
        $attributes = [];
        $afterKey = false;
        foreach ($column->qualifiers as $qualifier) {
            if ($qualifier instanceof ColumnPrimaryKey) {
                Check::input($key === null, 'CREATE TABLE can declare only one primary key.');
                Check::input($qualifier->options === [] && $qualifier->tablespace === null, 'A primary key requires default index parameters.');
                $key = $qualifier;
                $afterKey = true;
            } elseif ($afterKey && $qualifier instanceof ConstraintAttribute) {
                $attributes[] = $qualifier;
            } else {
                $qualifiers[] = $qualifier;
                $afterKey = false;
            }
        }
        if ($key === null) {
            return [$column, null];
        }
        $remaining = new ColumnDefinition($column->name, $column->type, $column->storage, $column->compression, $column->options, $qualifiers);
        $name = $key->name === null ? null : new Identifier($key->name->value);

        return [$remaining, new PrimaryKey([new Identifier($column->name->value)], name: $name, timing: $this->timing($attributes))];
    }

    /**
     * Resolves the ordered table-level key and payload references.
     * @throws InvalidConstruction When the key has unsupported index options
     */
    public function table(TablePrimaryKey $key): PrimaryKey
    {
        Check::input($key->options === [] && $key->tablespace === null, 'A primary key requires default index parameters.');
        $keys = [];
        foreach ($key->columns as $column) {
            $keys[] = new Identifier($column->value);
        }
        $included = [];
        foreach ($key->included as $column) {
            $included[] = new Identifier($column->value);
        }

        return new PrimaryKey($keys, $included, $key->name === null ? null : new Identifier($key->name->value), $this->timing($key->attributes));
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
                    Check::input($deferrable === null, 'A primary key can specify deferrability only once.');
                    $deferrable = $attribute === ConstraintAttribute::Deferrable;
                    break;
                case ConstraintAttribute::InitiallyImmediate:
                case ConstraintAttribute::InitiallyDeferred:
                    Check::input($deferred === null, 'A primary key can specify initial timing only once.');
                    $deferred = $attribute === ConstraintAttribute::InitiallyDeferred;
                    break;
                case ConstraintAttribute::NotValid:
                case ConstraintAttribute::NoInherit:
                    throw new InvalidConstruction('The attribute is outside the primary key definition.');
            }
        }
        Check::input($deferrable !== false || $deferred !== true, 'A not-deferrable primary key cannot initially be deferred.');

        return match (true) {
            $deferred === true => KeyTiming::InitiallyDeferred,
            $deferrable === true => KeyTiming::InitiallyImmediate,
            default => KeyTiming::NotDeferrable,
        };
    }
}
