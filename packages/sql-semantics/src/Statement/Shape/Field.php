<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * One output field of a query, with the facts the operation derived for it.
 *
 * A field belongs to the operation that derived it. It is a reading result,
 * not an input for building another query.
 *
 * @visibility public
 * @example Reading a field
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $field = $semantics->analyze('SELECT a AS x FROM t', [$table])->field('x');
 *     [$field->position, $field->name?->value, $field->column()?->name->value] // => [0, 'x', 'a']
 */
final class Field
{
    use Snapshot;

    /**
     * @var Name|null The output name, or null when the position has none
     */
    public readonly ?Name $name;

    /**
     * @var TypeFact What is known about the type
     */
    public readonly TypeFact $type;

    /**
     * @var Nullability Whether the field can be NULL
     */
    public readonly Nullability $nullability;

    /**
     * @param int $position The zero-based output position
     * @param OutputSlot $slot The output position with its name and facts
     * @param Scalar|null $expression The expression that computes the field; null when no single expression does, as for a set operation
     * @param Resolution|null $resolution The name resolution when the expression is a direct name use
     */
    public function __construct(
        public readonly int $position,
        public readonly OutputSlot $slot,
        public readonly ?Scalar $expression = null,
        public readonly ?Resolution $resolution = null,
    ) {
        $this->name = $slot->name;
        $this->type = $slot->type;
        $this->nullability = $slot->nullability;
    }

    /**
     * Answers the declared column the field directly returns, when it is one.
     */
    public function column(): ?Column
    {
        return $this->slot->declaration();
    }
}
