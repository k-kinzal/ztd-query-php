<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\View;

use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedLowering;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateSchema;
use SqlSemantics\Platform\Sqlite\Ir\SelectLowering;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound as ParsedCompound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView as ParsedCreateView;

/**
 * Translates the verified view query and names into the completed definition.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateViewLowering
{
    /**
     * Lowers the view destination, ordered names and checked body.
     * @throws InvalidConstruction When the statement has no completed view representation
     */
    public function lower(BoundStatement $operation, ParsedCreateView $statement): CreateView
    {
        $schema = (new CreateSchema())->resolve($operation, $statement->name, $statement->temporary);
        $query = $statement->query;
        $body = match (true) {
            $query instanceof ParsedCompound => (new CompoundLowering())->lower($operation, $query),
            $query instanceof ValuesClause => (new ValuesLowering())->lower($operation, $query),
            $query instanceof ParsedSelect && (new GroupedLowering())->applies($query) => (new GroupedLowering())->lower($operation, $query),
            $query instanceof ParsedSelect, $query instanceof WithQuery => (new SelectLowering())->lower($operation, $query),
            default => throw new InvalidConstruction('CREATE VIEW requires a checked query.'),
        };
        $declaration = $operation->facts->declarations[0] ?? null;
        Check::invariant($declaration !== null && $declaration->kind === RelationKind::View, 'The view operation has a named view declaration.');
        $columns = [];
        foreach ($declaration->columns as $column) {
            $columns[] = new Identifier($column->name->value);
        }

        return new CreateView($schema, new Identifier($statement->name->name->value), $columns, $body, $statement->ifNotExists);
    }
}
