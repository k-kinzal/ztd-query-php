<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy\Endpoint;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A shell command run by the database server, given as a text constant; binding never runs it.
 * @visibility public
 * @example Reading the command
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COPY t TO PROGRAM 'gzip > /tmp/t.gz'", strict: false);
 *     $statement->destination->command->text // => "'gzip > /tmp/t.gz'"
 */
final class CopyProgram implements CopyEndpoint
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $command)
    {
        if ($command->literalKind !== LiteralKind::Text || $command->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A COPY program is named by a PostgreSQL text constant.');
        }
    }
}
