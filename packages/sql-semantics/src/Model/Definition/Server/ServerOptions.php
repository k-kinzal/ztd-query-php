<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Server;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Connection options of a MySQL server definition; each omitted option keeps its previous or default value.
 * @visibility public
 * @example Reading the connection target
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'db.example', PORT 3307, DATABASE 'app')");
 *     [$statement->options->host, $statement->options->port, $statement->options->database] // => ['db.example', 3307, 'app']
 */
final class ServerOptions
{
    /**
     * Requires at least one option; the password is recorded as written and never hashed.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?string $user = null,
        public readonly ?string $host = null,
        public readonly ?string $database = null,
        public readonly ?string $owner = null,
        public readonly ?string $password = null,
        public readonly ?string $socket = null,
        public readonly ?int $port = null,
    ) {
        if ($user === null && $host === null && $database === null && $owner === null && $password === null && $socket === null && $port === null) {
            throw new InvalidStructure('A server definition requires at least one option.');
        }
        if ($port !== null && $port < 0) {
            throw new InvalidStructure('A server port cannot be negative.');
        }
    }
}
