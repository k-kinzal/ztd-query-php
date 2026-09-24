<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ConstraintTiming;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sets the checking time of the named deferrable constraints for the current transaction; the names are not resolved by binding.
 * @visibility public
 * @example Reading the named constraints
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SET CONSTRAINTS app.orders_fk, items_fk DEFERRED');
 *     [array_map(static fn ($name) => $name->parts, $statement->constraints), $statement->timing->value] // => [[['app', 'orders_fk'], ['items_fk']], 'DEFERRED']
 */
final class SetNamedConstraintsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<QualifiedName> $constraints Constraint names, optionally schema-qualified, in written order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $constraints, public readonly ConstraintTiming $timing)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('SET CONSTRAINTS requires PostgreSQL.');
        }
        Collections::nonEmpty($constraints);
        Collections::objects($constraints, QualifiedName::class);
        foreach ($constraints as $constraint) {
            if (count($constraint->parts) > 3) {
                throw new InvalidStructure('A constraint name has at most catalog, schema and constraint components.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the constraints and timing while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->constraints, $this->timing);
    }

    /**
     * Applies the timing to other constraints.
     * @param non-empty-list<QualifiedName> $constraints
     */
    public function withConstraints(array $constraints): self
    {
        return $this->changed(new self($this->origin, $constraints, $this->timing));
    }

    /**
     * Selects immediate or deferred checking.
     */
    public function withTiming(ConstraintTiming $timing): self
    {
        return $this->changed(new self($this->origin, $this->constraints, $timing));
    }
}
