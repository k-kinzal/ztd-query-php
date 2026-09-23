<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ServerInvariant;
use SqlSemantics\Model\Definition\Foreign\WrapperInvariant;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares a foreign server, its owning wrapper, and optional literal metadata.
 * @visibility public
 * @example Inspecting the wrapper selection
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
 *     $statement->wrapper // => 'fdw'
 */
final class CreateForeignServerStatement extends BoundStatement
{
    /**
     * @param list<ForeignOption> $options Initial connection options with unique names
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly string $wrapper,
        public readonly ?Literal $serverType = null,
        public readonly ?Literal $version = null,
        public readonly array $options = [],
        public readonly bool $ifNotExists = false,
    ) {
        ServerInvariant::target($origin, $name);
        WrapperInvariant::target($origin, $wrapper);
        ServerInvariant::text($serverType);
        ServerInvariant::text($version);
        WrapperInvariant::options($options);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the declaration while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->wrapper, $this->serverType, $this->version, $this->options, $this->ifNotExists);
    }

    /**
     * Replaces the server identity without mutating the original declaration.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->wrapper, $this->serverType, $this->version, $this->options, $this->ifNotExists));
    }

    /**
     * Replaces the wrapper and its dependent metadata and options together.
     * @param list<ForeignOption> $options Initial connection options with unique names
     */
    public function withDefinition(string $wrapper, ?Literal $serverType, ?Literal $version, array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $wrapper, $serverType, $version, $options, $this->ifNotExists));
    }

    /**
     * Changes the behavior when the server already exists.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->wrapper, $this->serverType, $this->version, $this->options, $ifNotExists));
    }
}
