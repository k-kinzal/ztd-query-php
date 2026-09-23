<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\WrapperInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares a wrapper with optional support functions and unique initial options.
 * @visibility public
 * @example Inspecting the named wrapper
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
 *     $statement->name // => 'fdw'
 */
final class CreateForeignDataWrapperStatement extends BoundStatement
{
    /**
     * @param list<ForeignOption> $options Ordered initial options
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly ?QualifiedName $handler = null,
        public readonly ?QualifiedName $validator = null,
        public readonly array $options = [],
    ) {
        WrapperInvariant::target($origin, $name);
        WrapperInvariant::functionName($handler);
        WrapperInvariant::functionName($validator);
        WrapperInvariant::options($options);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains all wrapper operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->handler, $this->validator, $this->options);
    }

    /**
     * Replaces the wrapper identity in a separately validated statement.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->handler, $this->validator, $this->options));
    }

    /**
     * Replaces the optional handler and validator declarations together.
     */
    public function withFunctions(?QualifiedName $handler, ?QualifiedName $validator): self
    {
        return $this->changed(new self($this->origin, $this->name, $handler, $validator, $this->options));
    }

    /**
     * @param list<ForeignOption> $options Replacement initial options with unique names
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->handler, $this->validator, $options));
    }
}
