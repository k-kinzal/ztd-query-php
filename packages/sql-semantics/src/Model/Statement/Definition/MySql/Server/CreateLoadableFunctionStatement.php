<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Server\LoadableResult;
use SqlSemantics\Model\Definition\Storage\StorageInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests CREATE FUNCTION ... SONAME, registering a loadable function from a shared library without loading it.
 * @visibility public
 * @example Inspecting a loadable aggregate function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE AGGREGATE FUNCTION avgcost RETURNS REAL SONAME 'udf_example.so'");
 *     [$statement->name, $statement->returns->value, $statement->library, $statement->aggregate] // => ['avgcost', 'REAL', 'udf_example.so', true]
 */
final class CreateLoadableFunctionStatement extends BoundStatement
{
    /**
     * The name is required and nonempty; IF NOT EXISTS requires a MySQL 8+ grammar.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly LoadableResult $returns, public readonly string $library, public readonly bool $aggregate = false, public readonly bool $ifNotExists = false)
    {
        if ($origin->dialect !== Dialect::MySql || $name === '') {
            throw new InvalidStructure('A loadable function requires MySQL and a nonempty name.');
        }
        if ($ifNotExists) {
            StorageInvariant::modern($origin, 'CREATE FUNCTION IF NOT EXISTS');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the registration while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->returns, $this->library, $this->aggregate, $this->ifNotExists);
    }

    /**
     * Replaces the function name.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->returns, $this->library, $this->aggregate, $this->ifNotExists));
    }

    /**
     * Replaces the declared result kind.
     * @throws InvalidStructure
     */
    public function withReturns(LoadableResult $returns): self
    {
        return $this->changed(new self($this->origin, $this->name, $returns, $this->library, $this->aggregate, $this->ifNotExists));
    }

    /**
     * Replaces the shared library file name.
     * @throws InvalidStructure
     */
    public function withLibrary(string $library): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->returns, $library, $this->aggregate, $this->ifNotExists));
    }

    /**
     * Declares the function as an aggregate or a scalar function.
     * @throws InvalidStructure
     */
    public function withAggregate(bool $aggregate): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->returns, $this->library, $aggregate, $this->ifNotExists));
    }

    /**
     * Adds or removes the IF NOT EXISTS policy.
     * @throws InvalidStructure
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->returns, $this->library, $this->aggregate, $ifNotExists));
    }
}
