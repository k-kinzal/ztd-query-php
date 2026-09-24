<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates an empty operator family for an index access method.
 * @visibility public
 * @example Creating an operator family
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY app.ints USING btree');
 *     $statement->name->parts // => ['app', 'ints']
 *     $statement->method // => 'btree'
 * @example Rejecting an over-qualified family name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY ints USING btree');
 *     $statement->withName(new \SqlSemantics\Model\Relation\QualifiedName(['a', 'b', 'c'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateOperatorFamilyStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly string $method)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::identifier($method);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->method);
    }

    /**
     * Replaces the family name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->method));
    }

    /**
     * Replaces the index access method.
     */
    public function withMethod(string $method): self
    {
        return $this->changed(new self($this->origin, $this->name, $method));
    }
}
