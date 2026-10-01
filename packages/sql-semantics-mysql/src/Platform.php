<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;

/**
 * Assembles MySql semantic behavior from independent core contracts.
 *
 * @visibility SqlSemantics
 */
final class Platform implements Contract
{
    /**
     * Retains the public language identity in all semantic types.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Configures the selected grammar release.
     */
    public function parser(?string $version = null): SqlParser
    {
        return new MySqlParser($version);
    }

    /**
     * Supplies the default declaration namespace.
     */
    public function defaultSchema(): string
    {
        return '';
    }

    /**
     * @return array{string, string}
     */
    public function statementNames(?string $version = null): array
    {
        return $version !== null && str_starts_with($version, 'mysql-5.')
            ? ['query', 'statement']
            : ['start_entry', 'simple_statement'];
    }

    /**
     * Maps the supported grammar productions onto semantic roles.
     */
    public function syntax(): Policy\SyntaxRules
    {
        return new Policy\SyntaxRules([
            'columnName' => ['ident'],
            'declaredType' => ['type'],
            'expression' => ['expr'],
            'createTable' => ['create_table_stmt'],
            'createHeader' => [],
            'tableName' => ['table_ident'],
            'tableConstraint' => ['table_constraint_def'],
            'columnReference' => ['simple_ident'],
            'identifierToken' => ['IDENT', 'IDENT_QUOTED'],
            'parameterToken' => ['PARAM_MARKER'],
            'projectionList' => ['select_item_list'],
            'projectionExpression' => ['expr', 'table_wild'],
            'projectionAlias' => ['select_alias'],
            'deleteStatement' => ['delete_stmt', 'delete'],
            'deleteChildren' => ['table_ident', 'opt_table_alias', 'where_clause', 'opt_where_clause', 'single_multi'],
            'deleteWrapper' => ['single_multi'],
            'deleteTable' => ['table_ident'],
            'deleteAlias' => ['opt_table_alias'],
            'deleteWhere' => ['where_clause', 'opt_where_clause'],
            'insertStatement' => ['insert_stmt', 'insert'],
            'selectStatement' => ['select_stmt', 'select'],
            'selectBody' => ['query_specification', 'select_part2', 'create_select'],
            'from' => ['from_clause', 'select_from'],
            'where' => ['where_clause', 'opt_where_clause'],
            'selectOptions' => ['select_options'],
            'orderingChildren' => ['expr', 'opt_ordering_direction', 'ordering_direction'],
            'orderingDirection' => ['opt_ordering_direction', 'ordering_direction'],
            'nullsOrder' => [],
            'stringToken' => ['TEXT_STRING'],
            'limit' => ['limit_clause'],
            'offset' => [],
            'paginationExpression' => ['expr', 'limit_option'],
            'selectChildren' => ['from_clause', 'where_clause', 'select_options', 'select_item_list', 'opt_from_clause', 'opt_where_clause', 'select_into', 'select_from', 'select_options_and_item_list', 'opt_select_from', 'table_expression', 'join_table_list', 'opt_order_clause', 'opt_limit_clause'],
            'selectWrapper' => ['select_into', 'select_from', 'select_options_and_item_list', 'opt_select_from', 'table_expression'],
            'unsupportedModifier' => ['with_clause', 'into_clause', 'opt_into', 'locking_clause', 'locking_clause_list', 'select_lock_type', 'opt_select_lock_type', 'procedure_analyse_clause', 'opt_procedure_analyse_clause'],
            'relation' => ['table_ref', 'table_reference'],
            'qualifiedExpression' => ['expr'],
            'qualifiedPart' => [],
        ]);
    }

    /**
     * Supplies names semantics.
     */
    public function names(): Policy\NameRules
    {
        return new NameRules();
    }

    /**
     * Supplies types semantics.
     */
    public function types(): Policy\TypeRules
    {
        return new TypeRules($this->dialect);
    }

    /**
     * Supplies schema semantics.
     */
    public function schema(): Policy\SchemaRules
    {
        return new SchemaRules();
    }

    /**
     * Supplies query semantics.
     */
    public function query(): Policy\QueryRules
    {
        return new QueryRules();
    }
    /**
     * Supplies semantic relation lowering.
     */
    public function relations(): Policy\RelationRules
    {
        return new SemanticRelations();
    }

    /**
     * Supplies semantic insertion lowering.
     */
    public function inserts(): Policy\InsertRules
    {
        return new SemanticInsert();
    }

}
