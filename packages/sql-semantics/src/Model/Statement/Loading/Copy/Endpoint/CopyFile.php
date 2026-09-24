<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy\Endpoint;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A file on the database server, named by a text constant; binding never opens it.
 * @visibility public
 * @example Reading the path
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COPY t FROM '/tmp/t.csv'", strict: false);
 *     $statement->input->path->text // => "'/tmp/t.csv'"
 */
final class CopyFile implements CopyEndpoint
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $path)
    {
        if ($path->literalKind !== LiteralKind::Text || $path->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A COPY file is named by a PostgreSQL text constant.');
        }
    }
}
