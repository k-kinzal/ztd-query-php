<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;

/**
 * A classified integrity condition; concrete forms require their own operands.
 *
 * @visibility public
 * @example Classifying a declared constraint
 *     $constraint = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER, CONSTRAINT pk PRIMARY KEY (a, b))')->tables[0]->constraints[0];
 *     $constraint->kind // => \SqlSemantics\Schema\ConstraintKind::PrimaryKey
 *     $constraint->name // => 'pk'
 *     $constraint->localColumns() // => ['a', 'b']
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
