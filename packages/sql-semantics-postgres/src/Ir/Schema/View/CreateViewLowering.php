<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView as ParsedCreateView;

/**
 * Resolves the effective persistent heading and closes the native definition.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CreateViewLowering
{
    /**
     * Publishes the complete view declaration from resolved operations and names.
     * @throws InvalidConstruction When the native view is outside the checked placements
     */
    public function lower(BoundStatement $operation, ParsedCreateView $statement): CreateView
    {
        $context = $operation->context;
        Check::input($context->profile->parameters === ParameterStyle::Native, 'CREATE VIEW requires the native language profile.');
        Check::input(
            !$statement->recursive && $statement->persistence === Persistence::Permanent && $statement->name->catalog === null,
            'CREATE VIEW requires a persistent ordinary view in an absolute schema.'
        );
        $schema = ($statement->name->schema ?? $context->declarationSchema)->value;
        $name = new ViewName(new Identifier($schema), new Identifier($statement->name->name->value));
        [$barrier, $invoker, $check] = (new ViewOptionsLowering())->lower($statement);
        $query = $statement->query;
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        $body = match (true) {
            $query instanceof ParsedSelect => (new SelectLowering())->lower($operation, $query),
            $query instanceof ValuesList => (new ValuesLowering())->lower($operation, $query),
            default => throw new InvalidConstruction('A persistent view needs a checked SELECT or standalone VALUES definition.'),
        };
        $fields = $operation->facts->query($query)->fields();
        Check::input($fields !== null && count($statement->columns) <= count($fields), 'The view needs a complete resolved heading.');
        $columns = [];
        foreach ($fields as $position => $field) {
            $column = $statement->columns[$position] ?? $field->name;
            Check::input($column !== null, 'Each view field needs a resolved catalog name.');
            $columns[] = new Identifier($column->value);
        }

        return new CreateView($name, $body, $columns, $statement->replace, $barrier, $invoker, $check);
    }
}
