<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use MySqlMemory\Dictionary\Binding\Declarations;
use MySqlMemory\Dictionary\Binding\ViewBinding;
use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Plan\Views as PlannedViews;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Statement\Declaration\Table;

/**
 * Binds a stored view using declarations supplied by a read-only catalog snapshot.
 * No SQL text, parser tree or dictionary mutation participates in rebinding.
 *
 * @visibility MySqlMemory
 */
final class Views
{
    /**
     * Freezes the completed dependency traversal as a value-only catalog snapshot.
     */
    public static function capture(Dictionary $dictionary, Settings $settings): Declarations
    {
        $bindings = new ViewBindings($dictionary, $settings);
        $schemas = [];
        foreach ($dictionary->schemas as $schema) {
            $schemas[$schema->name] = true;
            foreach ($schema->views as $view) {
                $bindings->resolve($view);
            }
        }
        return new Declarations($bindings->tables, $bindings->views, $schemas);
    }

    /**
     * Preserves the stored column names and their positions after dependencies change.
     *
     * @param list<Table> $declarations The persistent catalog, with dependencies already bound
     * @param bool $dependenciesValid Whether every referenced view is valid
     */
    public static function bind(View $view, array $declarations, Settings $settings, bool $dependenciesValid = true): ViewBinding
    {
        $stale = false;
        foreach ($view->tables as $declaration) {
            if (!in_array($declaration, $declarations, true)) {
                $stale = true;
            }
        }
        if (!$stale) {
            return new ViewBinding($view, $view->created, $view->declaration, array_keys($view->declaration->columns), $dependenciesValid);
        }
        $outputs = PlannedViews::outputs($view->created, $view->definition);
        $lexical = $view->created->profile()->lexical;
        $mode = new Mode($lexical->ansiQuotes, $lexical->pipesAsConcat, $lexical->highNotPrecedence, $lexical->noBackslashEscapes, $lexical->ignoreSpace);
        $platform = Platforms::of('mysql');
        $profile = $platform->profile('mysql-' . $settings->version, $mode, ParameterStyle::Native);
        $query = $view->definition;
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name($view->name), new \SqlSemantics\Statement\Identifier\Name($view->schema));
        $statement = new \SqlSemantics\Platform\MySql\Statement\View\CreateView(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition($name, $query, algorithm: \SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::from($view->algorithm)));
        $context = $platform->context($profile, [$view->database], $declarations, true)->withSession($settings->resolution());
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $statement, new \SqlSemantics\Statement\Source\SourceMap());
        \SqlSemantics\Diagnostic\Check::invariant($operation instanceof \SqlSemantics\Platform\MySql\Statement\View\CreateView, 'View binding preserves its concrete definition.');
        if ($operation->statementInformation()->diagnostics !== []) {
            return new ViewBinding($view, $operation, $view->declaration, [], false);
        }
        $names = PlannedViews::outputs($operation, $operation->definition->query);
        $positions = [];
        foreach ($outputs as $output) {
            $position = array_search($output, $names, true);
            if (!is_int($position)) {
                return new ViewBinding($view, $operation, $view->declaration, [], false);
            }
            $positions[] = $position;
        }
        $fields = $operation->declarations()[0]->columns;
        $columns = [];
        foreach ($view->declaration->columns as $index => $column) {
            $field = $fields[$positions[$index]] ?? null;
            $columns[] = $field !== null ? new \SqlSemantics\Statement\Declaration\Column($column->name, $field->type, $field->nullability, $field->generated, $field->expression) : $column;
        }
        $old = $view->declaration;
        $declaration = new Table($old->name, $old->profile, $columns, $old->implicit, $old->complete, $old->kind, $old->keys, $old->partitions);

        return new ViewBinding($view, $operation, $declaration, $positions, $dependenciesValid);
    }
}
