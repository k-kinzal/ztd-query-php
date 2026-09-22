<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;

/**
 * A classified integrity condition; concrete forms require their own operands.
 *
 * @visibility public
 */
abstract class TableConstraint
{
    /**
     * Integrity-condition category derived from the concrete constraint type.
     */
    public readonly ConstraintKind $kind;

    /**
     * Records the declared identity and diagnostic origin.
     */
    public function __construct(public readonly ?string $name, public readonly Node $source)
    {
        $this->kind = $this->operation();
    }

    /**
     * Returns the integrity operation selected by the concrete type.
     */
    abstract protected function operation(): ConstraintKind;

    /**
     * @return list<string>
     */
    abstract public function localColumns(): array;
}
