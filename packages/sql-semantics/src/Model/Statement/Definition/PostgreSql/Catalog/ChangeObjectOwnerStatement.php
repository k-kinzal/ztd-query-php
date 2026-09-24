<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Transfers ownership of one catalog object to a named or session role.
 * @visibility public
 * @example Transferring a schema to the session user
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SCHEMA app OWNER TO SESSION_USER');
 *     $statement->newOwner // => \SqlSemantics\Model\Configuration\Role\SessionRole::SessionUser
 */
final class ChangeObjectOwnerStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ObjectAddress $object, public readonly NamedRole|SessionRole $newOwner)
    {
        CatalogInvariant::dialect($origin);
        CatalogInvariant::owner($object);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the object and new owner while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->object, $this->newOwner);
    }

    /**
     * Replaces the owned object in a separately validated statement.
     */
    public function withObject(ObjectAddress $object): self
    {
        return $this->changed(new self($this->origin, $object, $this->newOwner));
    }

    /**
     * Replaces the destination role.
     */
    public function withNewOwner(NamedRole|SessionRole $newOwner): self
    {
        return $this->changed(new self($this->origin, $this->object, $newOwner));
    }
}
