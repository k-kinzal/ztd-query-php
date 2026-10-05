<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The definition of a routine written as a string constant: unanalysed text in the routine's language.
 *
 * Rule: PG-ROUTINE-SOURCE-001. Scope: `func_as` in `createfunc_opt_item: AS
 * func_as` of `CreateFunctionStmt`. Constructor: `RoutineSource`, held by
 * `RoutineDefinition`. Decision: the text is kept as the exact decoded
 * string, together with the name of the language it is written in, and is
 * not parsed, for every language including `sql`.
 * Rationale: the grammar gives the definition as a string constant whose
 * meaning depends on the language: an internal function name for `internal`,
 * an object file for `c`, SQL commands for `sql`, or text in a procedural
 * language (sql-createfunction.html, "definition"). Reading it is the job of
 * the language's handler and validator, not of the SQL grammar; the
 * procedural languages have no grammar shipped here. For `sql` the manual
 * states that the string form "is parsed at execution time", unlike an
 * SQL-standard body, which "is parsed at function definition time"
 * (sql-createfunction.html, `sql_body`): names in the text are resolved
 * against the catalog and search path of each call, and the text is
 * validated at definition time only when `check_function_bodies` is on
 * (runtime-config-client.html#GUC-CHECK-FUNCTION-BODIES), a session setting
 * that is not part of the language profile. Parsing the text here would
 * attach name resolutions and types to a definition for which PostgreSQL
 * fixes none, so the model states only what the server fixes: the language
 * and the text. An SQL-standard body (`RETURN` or `BEGIN ATOMIC`) is
 * structured and derived instead (PG-ROUTINE-BODY-001). The language is the
 * name of the routine's LANGUAGE option, decoded as the server looks it up;
 * it is null when no LANGUAGE is written, which the server rejects for a
 * string definition (PG-ROUTINE-CHECK-001). With several LANGUAGE options,
 * which the server rejects as conflicting, it is the first one.
 * Facts: none; the text holds no expression. Diagnostics: none of its own.
 * Termination: no recursion.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html,
 * https://www.postgresql.org/docs/17/runtime-config-client.html#GUC-CHECK-FUNCTION-BODIES,
 * `interpret_AS_clause` in `src/backend/commands/functioncmds.c` of PostgreSQL 17.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the language and the text of a routine definition
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE FUNCTION one() RETURNS int4 LANGUAGE sql AS \$\$ SELECT 1 \$\$");
 *     $source = $operation->statement->options[1]->source;
 *     [$source->language?->value, $source->text->value] // => ['sql', ' SELECT 1 ']
 */
final class RoutineSource implements Node
{
    use Snapshot;

    /**
     * @param Name|null $language The language the text is written in, as the routine's LANGUAGE option names it; null when none is written
     * @param StringConstant $text The text, decoded
     */
    public function __construct(public readonly ?Name $language, public readonly StringConstant $text)
    {
    }

    /**
     * Writes the text as a string constant.
     */
    public function render(Output $out): void
    {
        $out->node($this->text);
    }
}
