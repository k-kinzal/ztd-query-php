<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;
use SqlSemantics\Statement\Declaration\Table;

/**
 * Derives the query of a view definition and the declaration it stands for.
 *
 * Rule: MYSQL-VIEW-FACTS-001. The query is derived as an independent query.
 * The view declares the columns of MYSQL-TABLE-DECLARATION-001. A column
 * list of another length than the query result (ER_VIEW_WRONG_LIST) and a
 * repeated column name (ER_DUP_FIELDNAME) are diagnostics. Terminates: one
 * pass over the output fields.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewFacts
{
    /**
     * Derives the query and answers the declaration of the view, reporting its problems.
     */
    public function derive(ViewDefinition $definition, Derivation $derivation): Table
    {
        $output = $derivation->query($definition->query, $derivation->environment());
        if ($definition->columns !== null && $output->shape->complete() && count($definition->columns) !== count($output->projection)) {
            $derivation->report(new ViewColumnCount(count($definition->columns), count($output->projection)));
        }
        $table = (new TableDeclaration())->view($definition->name, $output, $definition->columns, $derivation->context->profile);
        (new TableProblems())->duplicates($table, $derivation);

        return $table;
    }
}
