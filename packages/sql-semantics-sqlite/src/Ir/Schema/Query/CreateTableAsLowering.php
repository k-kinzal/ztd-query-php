<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Query;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateSchema;
use SqlSemantics\Platform\Sqlite\Ir\SelectLowering;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs as ParsedCreateTableAs;

/**
 * Binds the destination and query roles before publishing a CREATE TABLE AS value.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateTableAsLowering
{
    /**
     * Checks and lowers the native population query.
     * @throws InvalidConstruction When the query cannot be represented in the admitted complete domain
     */
    public function lower(BoundStatement $operation, ParsedCreateTableAs $statement): CreateTableAs
    {
        $schema = (new CreateSchema())->resolve($operation, $statement->name, $statement->temporary);
        $query = $statement->query;
        $value = match (true) {
            $query instanceof ValuesClause => (new ValuesLowering())->lower($operation, $query),
            $query instanceof Select => (new SelectLowering())->lower($operation, $query),
            default => throw new InvalidConstruction('CREATE TABLE AS requires a source-free SELECT or VALUES.'),
        };

        return new CreateTableAs($schema, new Identifier($statement->name->name->value), $value, $statement->ifNotExists);
    }
}
