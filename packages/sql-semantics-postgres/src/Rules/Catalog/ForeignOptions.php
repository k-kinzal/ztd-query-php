<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Rendering\Output;

/**
 * Writes and checks the option lists of foreign-data commands.
 *
 * Rule: PG-FOREIGN-OPTION-001. An `OPTIONS ( ... )` list is written only
 * when it has options. A foreign-data wrapper reads one handler and one
 * validator: naming either twice is "conflicting or redundant options".
 * Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-createforeigndatawrapper.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ForeignOptions
{
    /**
     * Writes `OPTIONS ( ... )` when the list is not empty.
     *
     * @param list<GenericOption|AlteredOption> $options
     */
    public function write(Output $out, array $options): void
    {
        if ($options !== []) {
            $out->keyword('OPTIONS')->symbol('(')->list($options)->symbol(')');
        }
    }

    /**
     * Reports a handler or validator named twice.
     *
     * @param list<FunctionClause> $functions
     */
    public function functions(Derivation $derivation, array $functions): void
    {
        $names = [];
        foreach ($functions as $function) {
            $names[] = $function->role->option();
        }
        (new OptionChecks())->redundant($derivation, $names);
    }
}
