<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DefinitionQueryLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
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
    public function lower(BoundStatement $operation, ParsedCreateView $statement, ?Identifier $memberNamespace = null): CreateView
    {
        $context = $operation->context;
        Check::input($context->profile->parameters === ParameterStyle::Native, 'CREATE VIEW requires the native language profile.');
        Check::input(
            !$statement->recursive && $statement->persistence === Persistence::Permanent && $statement->name->catalog === null,
            'CREATE VIEW requires a persistent ordinary view in an absolute schema.'
        );
        $schema = $statement->name->schema->value ?? $memberNamespace->value ?? $context->declarationSchema->value;
        $name = new ViewName(new Identifier($schema), new Identifier($statement->name->name->value));
        [$barrier, $invoker, $check] = (new ViewOptionsLowering())->lower($statement);
        [$body, $columns] = (new DefinitionQueryLowering())->lower($operation, $statement->query, $statement->columns, resolvedTextOutput: true);

        return new CreateView($name, $body, $columns, $statement->replace, $barrier, $invoker, $check);
    }
}
