<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;

/**
 * Lowers a persistent relation's independent defining query and its ordered catalog names.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DefinitionQueryLowering
{
    /**
     * Supplies the complete query and effective catalog heading.
     * @param list<Name> $aliases The optional leading name replacements
     * @return array{Select|ValuesQuery, list<Identifier>}
     * @throws InvalidConstruction When the query or heading cannot be resolved
     */
    public function lower(BoundStatement $operation, Query $query, array $aliases, bool $resolvedTextOutput = false): array
    {
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        $body = match (true) {
            $query instanceof ParsedSelect => (new SelectLowering())->lower($operation, $query, resolvedTextOutput: $resolvedTextOutput),
            $query instanceof ValuesList => (new ValuesLowering())->lower($operation, $query),
            default => throw new InvalidConstruction('A persistent relation needs a checked SELECT or standalone VALUES definition.'),
        };
        $fields = $operation->facts->query($query)->fields();
        Check::input($fields !== null && count($aliases) <= count($fields), 'The relation needs a complete resolved heading.');
        $columns = [];
        foreach ($fields as $position => $field) {
            $column = $aliases[$position] ?? $field->name;
            Check::input($column !== null, 'Each field needs a resolved catalog name.');
            $columns[] = new Identifier($column->value);
        }

        return [$body, $columns];
    }
}
