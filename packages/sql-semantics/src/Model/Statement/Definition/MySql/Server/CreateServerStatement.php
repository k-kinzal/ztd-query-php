<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Server\ServerOptions;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests CREATE SERVER, recording a FEDERATED connection definition without connecting.
 * @visibility public
 * @example Inspecting the wrapper and user
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE SERVER remote FOREIGN DATA WRAPPER mysql OPTIONS (USER 'app')");
 *     [$statement->name, $statement->wrapper, $statement->options->user] // => ['remote', 'mysql', 'app']
 */
final class CreateServerStatement extends BoundStatement
{
    /**
     * The server name is required and nonempty.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly string $wrapper, public readonly ServerOptions $options)
    {
        if ($origin->dialect !== Dialect::MySql || $name === '') {
            throw new InvalidStructure('CREATE SERVER requires MySQL and a nonempty server name.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the definition while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->wrapper, $this->options);
    }

    /**
     * Replaces the server name.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->wrapper, $this->options));
    }

    /**
     * Replaces the foreign data wrapper name.
     * @throws InvalidStructure
     */
    public function withWrapper(string $wrapper): self
    {
        return $this->changed(new self($this->origin, $this->name, $wrapper, $this->options));
    }

    /**
     * Replaces the connection options.
     * @throws InvalidStructure
     */
    public function withOptions(ServerOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->wrapper, $options));
    }
}
