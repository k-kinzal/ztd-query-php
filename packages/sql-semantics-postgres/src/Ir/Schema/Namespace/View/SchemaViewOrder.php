<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks a namespace's persistent views against actual preceding relation members.
 * @visibility SqlSemantics
 */
final class SchemaViewOrder
{
    /**
     * Checks unique view targets and each exact local input against earlier member roles.
     * @param array<array-key, CreateTable|TableName|true> $occupied Checked earlier occupants
     * @param list<CreateView> $views
     * @return array<array-key, CreateTable|TableName|true> Occupants after the view phase
     */
    public static function check(?Identifier $name, bool $guard, GrammarRelease $release, array $occupied, array $views): array
    {
        if ($views === []) {
            return $occupied;
        }
        Check::input($name !== null && !$guard, 'Schema views require an unguarded, statically named namespace.');
        $earlier = [];
        $fields = [];
        foreach ($views as $view) {
            Check::input(
                $view->name->schema->value === $name->value && $view->query->release === $release,
                'A schema view must belong to its namespace and release.'
            );
            $coordinate = $view->name->view->value;
            $previous = $earlier[$coordinate] ?? null;
            Check::input(!isset($occupied[$coordinate]) || ($view->replace && $previous !== null), 'A schema view requires an unused relation name.');
            $source = $view->query instanceof Select ? $view->query->source : null;
            if ($source instanceof TableSource && $source->schema->value === $name->value) {
                $local = $occupied[$source->table->value] ?? null;
                SchemaViewInputs::check($source, $local instanceof CreateTable ? $local : null, $fields[$source->table->value] ?? null);
            }
            $definition = SchemaViewFields::of($view, $fields);
            if ($previous !== null) {
                SchemaViewReplacement::check($previous, $view, $fields[$coordinate], $definition);
            }
            $earlier[$coordinate] = $view;
            $fields[$coordinate] = $definition;
            $occupied[$coordinate] = true;
        }

        return $occupied;
    }
}
