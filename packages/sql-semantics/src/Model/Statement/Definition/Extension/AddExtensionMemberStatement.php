<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Extension;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Makes an existing object a member of an extension, so that it is dropped and dumped with the extension.
 * @visibility public
 * @example Reading the ADD member
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore ADD FUNCTION app.f(integer)');
 *     $statement->extension // => 'hstore'
 *     $statement->object->kind->value // => 'FUNCTION'
 * @example Rejecting a column as a ADD member
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore ADD FUNCTION app.f(integer)');
 *     $statement->withObject(new \SqlSemantics\Model\Definition\Catalog\RelationMemberIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\RelationMemberKind::Column, 'c', new \SqlSemantics\Model\Relation\QualifiedName(['t']))); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AddExtensionMemberStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $extension, public readonly ObjectAddress $object)
    {
        ExtensionInvariant::dialect($origin);
        ExtensionInvariant::names($extension);
        ExtensionInvariant::member($object);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the extension and member while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->extension, $this->object);
    }

    /**
     * Replaces the extension whose membership changes.
     */
    public function withExtension(string $extension): self
    {
        return $this->changed(new self($this->origin, $extension, $this->object));
    }

    /**
     * Replaces the member object in a separately validated statement.
     */
    public function withObject(ObjectAddress $object): self
    {
        return $this->changed(new self($this->origin, $this->extension, $object));
    }
}
