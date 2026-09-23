<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops one trigger identified without an owning-table clause in MySQL or SQLite.
 * @visibility public
 * @example Inspecting the trigger's name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP TRIGGER IF EXISTS app.audit');
 *     $statement->name->parts // => ['app', 'audit']
 */
final class DropTriggerStatement extends BoundStatement
{
    /**
     * The single target cannot carry PostgreSQL's required owner or dependency policy.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly bool $ifExists = false)
    {
        if ($origin->dialect === Dialect::PostgreSql) {
            throw new InvalidStructure('PostgreSQL trigger deletion requires its owning table.');
        }
        if (count($name->parts) > 2) {
            throw new InvalidStructure('A trigger name has at most one namespace qualifier.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the target and existence policy when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifExists);
    }

    /**
     * Replaces the single trigger target and validates its SQL identity.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists));
    }

    /**
     * Replaces the behavior for a missing trigger.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists));
    }
}
