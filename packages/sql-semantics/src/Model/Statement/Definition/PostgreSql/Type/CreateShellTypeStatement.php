<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a shell type: a placeholder name that a later base type definition completes.
 * @visibility public
 * @example Creating a shell type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TYPE app.box3d');
 *     $statement->name->parts // => ['app', 'box3d']
 *     $statement->toString() // => 'CREATE TYPE "app"."box3d"'
 */
final class CreateShellTypeStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
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
        return new self($origin, $this->name);
    }

    /**
     * Replaces the type name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name));
    }
}
