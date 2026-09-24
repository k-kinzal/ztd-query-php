<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Extension;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Registers a table or index access method implemented by a handler function.
 * @visibility public
 * @example Reading the access method
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD heap2 TYPE TABLE HANDLER heap_tableam_handler');
 *     $statement->name // => 'heap2'
 *     $statement->type // => \SqlSemantics\Model\Statement\Definition\Extension\AccessMethodKind::Table
 *     $statement->handler->parts // => ['heap_tableam_handler']
 * @example Rejecting an empty access method name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD bloom2 TYPE INDEX HANDLER blhandler');
 *     $statement->withName(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateAccessMethodStatement extends BoundStatement
{
    /**
     * @param AccessMethodKind $type Kind of relation the method implements (TYPE)
     * @param QualifiedName $handler Function returning the method's interface (HANDLER)
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly AccessMethodKind $type, public readonly QualifiedName $handler)
    {
        ExtensionInvariant::dialect($origin);
        ExtensionInvariant::names($name);
        ExtensionInvariant::function($handler);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the access method definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->type, $this->handler);
    }

    /**
     * Replaces the access method name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->type, $this->handler));
    }

    /**
     * Replaces the kind of relation the method implements.
     */
    public function withType(AccessMethodKind $type): self
    {
        return $this->changed(new self($this->origin, $this->name, $type, $this->handler));
    }

    /**
     * Replaces the handler function.
     */
    public function withHandler(QualifiedName $handler): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $handler));
    }
}
