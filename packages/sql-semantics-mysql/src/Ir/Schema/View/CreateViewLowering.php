<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewColumns;
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
        Check::input(
            $definition->definer === null && !($statement->orReplace && $definition->ifNotExists),
            'CREATE VIEW needs an implicit definer and one admitted creation policy.'
        );
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
            $fact = $operation->facts->query($definition->query);
            $fields = (new TableDeclaration())->settled($fact);
            $assign = new ViewColumns($operation->context->profile);
            $generated = $assign->generated($definition->query, $fields);
            Check::input(count($generated) === count($query->columns), 'A view heading must resolve every result position.');
            $labels = array_map(static fn (ResultColumn $column): string => $column->name->value, $query->columns);
            foreach ($assign->allocate($labels, $generated) as $label) {
                Check::input($label !== null, 'A view heading must resolve every result name.');
                $columns[] = new ViewColumn(new Identifier($label));
            }
        } else {
            $names = new ViewCatalogHeading();
            foreach ($definition->columns as $column) {
                $columns[] = $names->explicit($column->value, $query->release);
            }
        }
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
            $check
        );
    }
}
