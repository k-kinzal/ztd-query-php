<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Dictionary\View;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Tracks stored definitions, not ephemeral view bindings, for preparation invalidation.
 * A view's dependencies belong to the permanent catalog even when its caller has shadows.
 *
 * @visibility MySqlMemory
 */
final class Dependencies
{
    /**
     * Captures every direct declaration and each view's transitive persistent dependencies.
     *
     * @param list<Table|DeclaredTable> $tables The declarations or resolved occurrences of the prepared statement
     * @return list<array{string, string, Table|View|null, bool}> Schema, name, identity and permanent-only lookup
     */
    public static function capture(array $tables, Session $session): array
    {
        $declarations = \MySqlMemory\Session\Statement\Catalog::snapshot($session);
        $dependencies = [];
        $visited = [];
        $pending = [];
        foreach (array_reverse($tables) as $table) {
            $pending[] = $table instanceof DeclaredTable ? [$table->table, false, $table->name->schema?->value] : [$table, false, $table->name->schema?->value];
        }
        while ($pending !== []) {
            [$table, $permanent, $resolvedSchema] = array_pop($pending);
            $schema = $resolvedSchema ?? $session->variables->database;
            $name = $table->name->name->value;
            if ($table->kind !== RelationKind::View) {
                $dependencies[] = [$schema, $name, $table, $permanent];
                continue;
            }
            $view = $session->instance->dictionary->schema($schema)->views[$name] ?? null;
            $dependencies[] = [$schema, $name, $view, $permanent];
            if ($view === null || isset($visited[spl_object_id($view)])) {
                continue;
            }
            $visited[spl_object_id($view)] = true;
            $binding = $declarations->view($view);
            foreach (array_reverse(\MySqlMemory\Plan\Views::tables($binding->operation, $binding->query())) as $dependency) {
                $pending[] = [$dependency, true, $dependency->name->schema?->value];
            }
            foreach ($binding->operation->statementInformation()->diagnostics as $diagnostic) {
                if ($diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable) {
                    $dependencies[] = [$diagnostic->name->schema->value ?? $view->database, $diagnostic->name->name->value, null, true];
                }
            }
        }
        return $dependencies;
    }
}
