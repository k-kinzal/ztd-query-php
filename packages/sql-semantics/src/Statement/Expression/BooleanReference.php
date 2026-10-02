<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * A SQLite TRUE/FALSE lookup, with its truth literal used only when no column matches.
 *
 * It remains a distinct expression because IS TRUE has boolean-test semantics
 * when lookup selects the literal, whereas IS 1 compares values.
 * @visibility public
 * @example Reading the fallback of an unshadowed truth identifier
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $reference = new \SqlSemantics\Statement\Expression\ColumnReference(new \SqlSemantics\Statement\Relation\Scope($catalog), new \SqlSemantics\Statement\Identifier\Name('TRUE'));
 *     (new \SqlSemantics\Statement\Expression\BooleanReference($reference))->fallback // => 1
 */
final class BooleanReference implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var 0|1
     */
    public readonly int $fallback;

    /**
     * Retains the lookup even when declarations do not yet settle column versus literal.
     */
    public function __construct(public readonly ColumnReference $column)
    {
        \SqlSemantics\Statement\Validation\Check::input($column->qualifier === null && $column->name->quote === Quote::None, 'A truth identifier must be bare and unqualified.');
        $name = strtoupper($column->name->value);
        \SqlSemantics\Statement\Validation\Check::input(in_array($name, ['TRUE', 'FALSE'], true), 'Only TRUE and FALSE have a truth literal alternative.');
        $this->fallback = $name === 'TRUE' ? 1 : 0;
    }

    /**
     * Uses the integer truth type only after ruling out a column with that name.
     */
    public function type(): TypeDescriptor|Unresolved|Invalid|NullDomain|SqliteNumericDomain|SqliteChoiceDomain
    {
        return $this->column->resolution instanceof MissingColumn ? new TypeDescriptor(Builtin::Integer) : $this->column->type();
    }

    /**
     * A selected truth literal is non-NULL; a selected column retains its own NULL fact.
     */
    public function nullability(): Nullability
    {
        return $this->column->resolution instanceof MissingColumn ? Nullability::NotNull : $this->column->nullability();
    }

    /**
     * Keeps the name lookup owned by the original scope even when it selects a literal.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [$this->column];
    }

    /**
     * Preserves the truth identifier and the original spelling of its output label.
     */
    public function toString(): string
    {
        return $this->column->toString();
    }
}
