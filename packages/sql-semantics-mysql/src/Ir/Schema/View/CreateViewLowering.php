<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\View\Definer\ViewDefinerLowering;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as ParsedCreateView;
use SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption as ParsedViewCheckOption;

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
        Check::input(!($statement->orReplace && $definition->ifNotExists), 'CREATE VIEW needs one admitted creation policy.');
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
        $columns = (new ViewHeadingLowering())->lower($operation, $definition, $query);
        $policy = match (true) {
            $statement->orReplace => ViewCreationPolicy::Replace,
            $definition->ifNotExists => ViewCreationPolicy::Preserve,
            default => ViewCreationPolicy::RequireNew,
        };

        $check = match ($definition->check) {
            null => ViewCheckOption::None,
            ParsedViewCheckOption::Local => ViewCheckOption::Local,
            ParsedViewCheckOption::Unqualified, ParsedViewCheckOption::Cascaded => ViewCheckOption::Cascaded,
        };

        return new CreateView(
            $name,
            $query,
            $columns,
            $policy,
            ViewAlgorithm::from($definition->algorithm->value ?? 'UNDEFINED'),
            ViewSecurity::from($definition->security->value ?? 'DEFINER'),
            $check,
            (new ViewDefinerLowering())->lower($definition->definer, $query->release)
        );
    }
}
