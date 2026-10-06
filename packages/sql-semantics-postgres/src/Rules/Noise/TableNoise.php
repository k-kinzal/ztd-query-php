<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the table family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class TableNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "column_name [ WITH OPTIONS ] [ column_constraint [ ... ] ]": the words are optional. https://www.postgresql.org/docs/17/sql-createtable.html
            'columnOptions: ColId WITH OPTIONS ColQualList' => [1, 2],
            // "WITHOUT OIDS: Backward-compatible syntax for declaring a table WITHOUT OIDS, creating a table WITH OIDS is not supported anymore." https://www.postgresql.org/docs/17/sql-createtable.html
            'OptWith: WITHOUT OIDS' => [0, 1],
            // "INCREMENT [ BY ] increment": the word is optional. https://www.postgresql.org/docs/17/sql-createsequence.html
            'opt_by: BY' => [0],
            // "ADD [ COLUMN ] [ IF NOT EXISTS ] column_name": the word is optional. https://www.postgresql.org/docs/17/sql-altertable.html
            'alter_table_cmd: ADD_P COLUMN columnDef' => [1],
            'alter_table_cmd: ADD_P COLUMN IF_P NOT EXISTS columnDef' => [1],
            // "FOR [ EACH ] { ROW | STATEMENT }": the word is optional. https://www.postgresql.org/docs/17/sql-createtrigger.html
            'TriggerForOptEach: EACH' => [0],
            // "the keywords FUNCTION and PROCEDURE are equivalent ... The use of the keyword PROCEDURE here is historical and deprecated." https://www.postgresql.org/docs/17/sql-createtrigger.html
            'FUNCTION_or_PROCEDURE: FUNCTION' => [0],
            'FUNCTION_or_PROCEDURE: PROCEDURE' => [0],
            // The semicolons separate the commands of a multi-command rule action; an empty command is dropped. https://www.postgresql.org/docs/17/sql-createrule.html
            'RuleActionMulti: RuleActionMulti ; RuleActionStmtOrEmpty' => [1],
            // The commas separate trigger arguments, which the model keeps as a list; the grammar admits an empty first argument
            // before a comma, which the server drops (`TriggerFuncArgs: /* EMPTY */ { $$ = NIL; }`). https://www.postgresql.org/docs/17/sql-createtrigger.html
            'TriggerFuncArgs: TriggerFuncArgs , TriggerFuncArg' => [1],
        ];
    }
}
