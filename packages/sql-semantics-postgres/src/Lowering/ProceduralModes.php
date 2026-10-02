<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;

/**
 * The productions the grammar keeps for PL/pgSQL and for parsing a lone type name.
 *
 * Rule: PG-STATEMENT-002. Scope: the alternatives of `parse_toplevel` that
 * start with a mode token, `PLpgSQL_Expr`, `PLAssignStmt`, `plassign_target`
 * and `plassign_equals`. The server enters them by pushing a mode token in
 * front of the text when PL/pgSQL compiles an expression or assignment, or
 * when a type name is parsed on its own; the scanner never produces a mode
 * token from SQL text, so no SQL statement reaches these productions. A tree
 * that contains one was not parsed from SQL text and is rejected as outside
 * the grammar of SQL statements.
 * Source: https://www.postgresql.org/docs/17/plpgsql-implementation.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ProceduralModes
{
    /**
     * The productions no SQL text reaches.
     */
    public const SIGNATURES = [
        'parse_toplevel: MODE_TYPE_NAME Typename',
        'parse_toplevel: MODE_PLPGSQL_EXPR PLpgSQL_Expr',
        'parse_toplevel: MODE_PLPGSQL_ASSIGN1 PLAssignStmt',
        'parse_toplevel: MODE_PLPGSQL_ASSIGN2 PLAssignStmt',
        'parse_toplevel: MODE_PLPGSQL_ASSIGN3 PLAssignStmt',
        'PLpgSQL_Expr: opt_distinct_clause opt_target_list from_clause where_clause group_clause having_clause window_clause opt_sort_clause opt_select_limit opt_for_locking_clause',
        'PLAssignStmt: plassign_target opt_indirection plassign_equals PLpgSQL_Expr',
        'plassign_target: ColId',
        'plassign_target: PARAM',
        'plassign_equals: COLON_EQUALS',
        'plassign_equals: =',
    ];

    /**
     * Rejects a tree rooted at a production of a parser mode.
     *
     * @throws AnalysisException When the production belongs to a parser mode
     * @throws ImplementationGap When the production is not one of the listed ones
     */
    public function reject(Form $form): never
    {
        if (in_array($form->signature, self::SIGNATURES, true)) {
            throw new AnalysisException('The text is not an SQL statement: ' . $form->signature . ' is entered only through a parser mode of the server.');
        }
        throw ImplementationGap::production($form);
    }
}
