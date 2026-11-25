<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as ParsedCreateView;

/**
 * Resolves a persistent declaration into its checked native heading and body.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateViewLowering
{
    /**
     * Binds the external identity and the catalog-visible position names.
     */
    public function lower(BoundStatement $operation, ParsedCreateView $statement): CreateView
    {
        $definition = $statement->definition;
        Check::input($definition->definer === null && $definition->check === null
            && !($statement->orReplace && $definition->ifNotExists), 'CREATE VIEW needs an implicit definer and one admitted creation policy without a check option.');
        Check::input($definition->query instanceof ParsedSelect, 'A checked view requires one ordinary SELECT block.');
        Check::input($operation->context->relationNames === Comparison::Sensitive, 'CREATE VIEW requires the native case-sensitive relation profile.');
        $schema = ($definition->name->schema ?? $operation->context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED && $definition->name->catalog === null, 'CREATE VIEW requires an absolute view identity.');
        $name = new ViewName(new Identifier($schema), new Identifier($definition->name->name->value));
        $aliases = new ViewProjectionAliases();
        foreach ($definition->query->items as $item) {
            if ($item instanceof SelectExpression && $item->alias !== null) {
                $aliases->check($item->alias->value);
            }
        }
        $query = (new SelectLowering())->lower($operation, $definition->query);
        $columns = [];
        if ($definition->columns === null) {
            foreach ($query->columns as $column) {
                Check::input(!$column->name instanceof IntrinsicIntegerName, 'A view requires an explicit name for a long intrinsic integer heading.');
                $columns[] = new ViewColumn(new Identifier($column->name->value));
            }
        } else {
            foreach ($definition->columns as $column) {
                $columns[] = new ViewColumn(new Identifier($column->value));
            }
        }
        $policy = match (true) {
            $statement->orReplace => ViewCreationPolicy::Replace,
            $definition->ifNotExists => ViewCreationPolicy::Preserve,
            default => ViewCreationPolicy::RequireNew,
        };

        return new CreateView(
            $name,
            $query,
            $columns,
            $policy,
            ViewAlgorithm::from($definition->algorithm->value ?? 'UNDEFINED'),
            ViewSecurity::from($definition->security->value ?? 'DEFINER')
        );
    }
}
