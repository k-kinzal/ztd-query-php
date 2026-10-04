<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Show;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateEvent;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateFunction;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureCode;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the SHOW statements of MySQL 8.0 and later about databases, tables and stored programs.
 *
 * Rule: MYSQL-SHOW-OBJECT-LOWERING-001. Scope: show_databases_stmt,
 * show_tables_stmt, show_triggers_stmt, show_events_stmt,
 * show_table_status_stmt, show_open_tables_stmt, show_columns_stmt,
 * show_keys_stmt, show_create_*_stmt (but USER), show_procedure_*_stmt,
 * show_function_*_stmt; the server statements are lowered by
 * MYSQL-SHOW-SERVER-LOWERING-001. Terminates: every part is a strict
 * subtree. Source: https://dev.mysql.com/doc/refman/8.4/en/show.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class ObjectRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of a SHOW rule about schema objects.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $clauses = new ClauseRule($this->lowering);
        switch ($form->signature) {
            case 'show_databases_stmt: SHOW DATABASES opt_wild_or_where':
                return new ShowDatabases($clauses->filter($form->node(2)));
            case 'show_tables_stmt: SHOW opt_show_cmd_type TABLES opt_db opt_wild_or_where':
                return new ShowTables($clauses->listing($form->node(1)), $clauses->database($form->node(3)), $clauses->filter($form->node(4)));
            case 'show_triggers_stmt: SHOW opt_full TRIGGERS_SYM opt_db opt_wild_or_where':
                return new ShowTriggers($clauses->listing($form->node(1)) !== null, $clauses->database($form->node(3)), $clauses->filter($form->node(4)));
            case 'show_events_stmt: SHOW EVENTS_SYM opt_db opt_wild_or_where':
                return new ShowEvents($clauses->database($form->node(2)), $clauses->filter($form->node(3)));
            case 'show_table_status_stmt: SHOW TABLE_SYM STATUS_SYM opt_db opt_wild_or_where':
                return new ShowTableStatus($clauses->database($form->node(3)), $clauses->filter($form->node(4)));
            case 'show_open_tables_stmt: SHOW OPEN_SYM TABLES opt_db opt_wild_or_where':
                return new ShowOpenTables($clauses->database($form->node(3)), $clauses->filter($form->node(4)));
            case 'show_columns_stmt: SHOW opt_show_cmd_type COLUMNS from_or_in table_ident opt_db opt_wild_or_where':
                $clauses->preposition($form->node(3));

                return new ShowColumns($this->table($form, 4), $clauses->listing($form->node(1)), $clauses->database($form->node(5)), $clauses->filter($form->node(6)));
            case 'show_keys_stmt: SHOW opt_extended keys_or_index from_or_in table_ident opt_db opt_where_clause':
                $this->lowering->options->skip($form->node(2));
                $clauses->preposition($form->node(3));

                return new ShowKeys($this->table($form, 4), $clauses->extended($form->node(1)), $clauses->database($form->node(5)), $clauses->where($form->node(6)));
            default:
                return $this->definition($form);
        }
    }

    /**
     * Lowers SHOW CREATE and the SHOW statements about stored programs.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function definition(Form $form): Statement
    {
        $names = $this->lowering->names;
        $clauses = new ClauseRule($this->lowering);
        switch ($form->signature) {
            case 'show_create_database_stmt: SHOW CREATE DATABASE opt_if_not_exists ident':
                return new ShowCreateDatabase($names->identifier($form->node(4)), $this->lowering->options->present($form->node(3)));
            case 'show_create_table_stmt: SHOW CREATE TABLE_SYM table_ident':
                return new ShowCreateTable($this->table($form, 3));
            case 'show_create_view_stmt: SHOW CREATE VIEW_SYM table_ident':
                return new ShowCreateView($this->table($form, 3));
            case 'show_create_procedure_stmt: SHOW CREATE PROCEDURE_SYM sp_name':
                return new ShowCreateProcedure($names->qualified($form->node(3)));
            case 'show_create_function_stmt: SHOW CREATE FUNCTION_SYM sp_name':
                return new ShowCreateFunction($names->qualified($form->node(3)));
            case 'show_create_trigger_stmt: SHOW CREATE TRIGGER_SYM sp_name':
                return new ShowCreateTrigger($names->qualified($form->node(3)));
            case 'show_create_event_stmt: SHOW CREATE EVENT_SYM sp_name':
                return new ShowCreateEvent($names->qualified($form->node(3)));
            case 'show_procedure_status_stmt: SHOW PROCEDURE_SYM STATUS_SYM opt_wild_or_where':
                return new ShowProcedureStatus($clauses->filter($form->node(3)));
            case 'show_function_status_stmt: SHOW FUNCTION_SYM STATUS_SYM opt_wild_or_where':
                return new ShowFunctionStatus($clauses->filter($form->node(3)));
            case 'show_procedure_code_stmt: SHOW PROCEDURE_SYM CODE_SYM sp_name':
                return new ShowProcedureCode($names->qualified($form->node(3)));
            case 'show_function_code_stmt: SHOW FUNCTION_SYM CODE_SYM sp_name':
                return new ShowFunctionCode($names->qualified($form->node(3)));
            default:
                return (new ServerRule($this->lowering))->statement($form);
        }
    }

    /**
     * Lowers the table name at a position into the inspected table.
     */
    public function table(Form $form, int $position): InspectedTable
    {
        return new InspectedTable($this->lowering->names->qualified($form->node($position)));
    }
}
