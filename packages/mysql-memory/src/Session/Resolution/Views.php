<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Plan\Views as PlannedViews;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;

/**
 * Refreshes stored view structures against the current catalog before planning.
 *
 * No SQL text or parser tree is retained or read when a view is rebound.
 *
 * @visibility MySqlMemory
 */
final class Views
{
    /**
     * Resolves the query of a view again when a table it reads has changed, and answers the position of each column of the view in the output of the query.
     *
     * @return list<int>
     *
     * @throws SqlError When the query no longer resolves to the columns of the view (ER_VIEW_INVALID)
     */
    public static function refresh(View $view, Dictionary $dictionary, Settings $settings): array
    {
        $identity = $view->positions === [] ? array_keys($view->declaration->columns) : $view->positions;
        $stale = false;
        foreach ($view->tables as $key => $declaration) {
            [$schema, $name] = explode('.', $key, 2);
            if (PlannedViews::declaration($dictionary, $schema, $name) !== $declaration) {
                $stale = true;
            }
        }
        if (!$stale) {
            return $identity;
        }
        $outputs = PlannedViews::outputs($view->created, $view->definition);
        $lexical = $view->created->profile()->lexical;
        $mode = new Mode($lexical->ansiQuotes, $lexical->pipesAsConcat, $lexical->highNotPrecedence, $lexical->noBackslashEscapes, $lexical->ignoreSpace);
        $semantics = new Semantics(Dialect::MySql, 'mysql-' . $settings->version, $mode, ParameterStyle::Native);
        $query = $view->definition;
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name($view->name), new \SqlSemantics\Statement\Identifier\Name($view->schema));
        $statement = new \SqlSemantics\Platform\MySql\Statement\View\CreateView(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition($name, $query));
        $operation = new Operation($semantics->context($dictionary->declarations(), true, new SearchPath($view->database), $settings->resolution()), $statement);
        $view->operation = $operation;
        if ($operation->facts->diagnostics !== []) {
            throw SchemaError::ViewInvalid->error($view->schema, $view->name);
        }
        $names = PlannedViews::outputs($operation, $query);
        $positions = [];
        foreach ($outputs as $output) {
            $position = array_search($output, $names, true);
            if (!is_int($position)) {
                throw SchemaError::ViewInvalid->error($view->schema, $view->name);
            }
            $positions[] = $position;
        }
        $view->query = $query;
        $view->tables = PlannedViews::tables($operation, $query, $view->database);
        $view->positions = $positions;
        $declarations = $operation->declarations()[0]->columns;
        $columns = [];
        foreach ($view->declaration->columns as $index => $column) {
            $field = $declarations[$positions[$index]] ?? null;
            $columns[] = $field !== null ? new \SqlSemantics\Statement\Declaration\Column($column->name, $field->type, $field->nullability) : $column;
        }
        $old = $view->declaration;
        $view->declaration = new Table($old->name, $old->profile, $columns, $old->implicit, $old->complete, $old->kind, $old->keys, $old->partitions);

        $view->valid = true;

        return $positions;
    }

    /**
     * Resolves again the views whose tables have changed, so that statements are resolved against the columns they now return; a view that no longer resolves is left as it is.
     */
    public static function refreshAll(Dictionary $dictionary, Settings $settings): void
    {
        $views = [];
        foreach ($dictionary->schemas as $schema) {
            array_push($views, ...array_values($schema->views));
        }
        for ($pass = 0; $pass <= count($views); $pass++) {
            $changed = false;
            foreach ($views as $view) {
                $declaration = $view->declaration;
                try {
                    self::refresh($view, $dictionary, $settings);
                } catch (SqlError) {
                    $view->valid = false;
                    continue;
                }
                $changed = $changed || $view->declaration !== $declaration;
            }
            if (!$changed) {
                return;
            }
        }
    }
}
