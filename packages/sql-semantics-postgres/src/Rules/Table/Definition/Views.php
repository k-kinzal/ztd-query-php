<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Derives CREATE VIEW.
 *
 * Rule: PG-VIEW-001. The query is derived at the top level; for a RECURSIVE
 * view the view name is bound in it as a recursive common table with the
 * written column names ("CREATE RECURSIVE VIEW name (columns) AS SELECT
 * ...; is equivalent to CREATE VIEW name AS WITH RECURSIVE name (columns) AS
 * (SELECT ...) SELECT columns FROM name;"). The view is declared by
 * PG-QUERY-TABLE-001 with the NULL facts of the query and without system
 * columns. UNLOGGED is reported: views have no storage.
 * Source: https://www.postgresql.org/docs/17/sql-createview.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Views
{
    /**
     * Derives a view; `$schema` is the schema of an enclosing CREATE SCHEMA.
     */
    public function derive(CreateView $view, Derivation $derivation, ?Name $schema): void
    {
        $environment = $derivation->environment();
        if ($view->recursive && $view->name->schema === null) {
            $environment = new Environment($environment->context, null, [], [new CommonBinding($view->name->name, $view, new RowShape([]))]);
        }
        $fact = $derivation->query($view->query, $environment);
        if ($view->persistence === Persistence::Unlogged) {
            $derivation->report(new DefinitionProblem(DefinitionRule::UnloggedView));
        } else {
            (new CreationSchemas())->check($derivation, $view->name, $view->persistence);
        }
        $rules = new QueryTables();
        $derivation->declare($rules->table($derivation, $rules->name($view->name, $view->persistence, $schema), $fact, $view->columns, false, false, DefinitionRule::ViewColumnCount));
    }
}
