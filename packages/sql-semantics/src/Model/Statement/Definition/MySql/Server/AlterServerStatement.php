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
 * Requests ALTER SERVER, replacing only the connection options it names.
 * @visibility public
 * @example Inspecting a changed socket
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER SERVER remote OPTIONS (SOCKET '/tmp/mysql.sock')");
 *     [$statement->name, $statement->options->socket, $statement->options->host] // => ['remote', '/tmp/mysql.sock', null]
 */
final class AlterServerStatement extends BoundStatement
{
    /**
     * The server is named as written; an unknown server is an execution error, not a binding error.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly ServerOptions $options)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('ALTER SERVER requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the alteration while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->options);
    }

    /**
     * Replaces the altered server.
     * @throws InvalidStructure
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the changed connection options.
     * @throws InvalidStructure
     */
    public function withOptions(ServerOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
