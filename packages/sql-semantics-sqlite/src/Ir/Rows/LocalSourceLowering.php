<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

/**
 * Resolves a query-local input against its exact definition.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class LocalSourceLowering
{
    /**
     * Converts the definition only after checking that the body resolves to it.
     */
    public function lower(BoundStatement $operation, WithQuery $statement): LocalSource
    {
        Check::input(!$statement->with->recursive && count($statement->with->tables) === 1, 'Complete SQLite IR currently supports one nonrecursive local source.');
        $definition = $statement->with->tables[0];
        Check::input(
            $definition->query instanceof ValuesClause && $definition->columns !== [] && $definition->materialization === null,
            'The local source requires named VALUES without a materialization hint.',
        );
        $body = $statement->body;
        Check::input($body instanceof ParsedSelect && $body->from instanceof TableInput && $body->from->index === null, 'A local source requires one SELECT table input.');
        $reference = $operation->facts->relation($body->from)->table;
        Check::input($reference instanceof CommonTable && $reference->definition === $definition, 'The SELECT input must resolve to this local definition.');
        $columns = [];
        foreach ($definition->columns as $column) {
            $columns[] = new Identifier($column->name->value);
        }

        return new LocalSource((new ValuesLowering())->lower($operation, $definition->query), $columns);
    }
}
