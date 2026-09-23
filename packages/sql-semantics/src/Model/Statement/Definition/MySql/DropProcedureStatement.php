<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Deletes one MySQL procedure selected by name, without an overload signature.
 * @visibility public
 * @example Inspecting a named deletion
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP PROCEDURE app.f');
 *     $statement->name->parts // => ['app', 'f']
 */
final class DropProcedureStatement extends BoundStatement
{
    /**
     * Requires one unqualified or database-qualified MySQL routine name.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly bool $ifExists = false)
    {
        if ($origin->dialect !== Dialect::MySql || count($name->parts) > 2) {
            throw new InvalidStructure('A MySQL routine deletion requires one local or database-qualified name.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifExists);
    }

    /**
     * Replaces the target without changing the original operation.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists));
    }

    /**
     * Replaces the behavior when the target does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists));
    }
}
