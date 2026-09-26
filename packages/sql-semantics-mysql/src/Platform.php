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
     * Loads this package's statement construction map for the resolved release.
     */
    public function values(string $version): \SqlSemantics\Core\Analysis\ValueReader
    {
        return \SqlSemantics\Core\Analysis\ValueReader::fromFile(dirname(__DIR__) . '/resources/mapping/' . basename($version) . '.php');
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
    public function statementNames(): array
    {
        return ['start_entry', 'simple_statement'];
    }

    /**
     * Maps the supported grammar productions onto semantic roles.
     */
    public function syntax(): Policy\SyntaxRules
    {
        return new Policy\SyntaxRules([
            'autoIncrement' => [],
            'dropTableName' => ['table_ident'],
            'generationStorage' => ['opt_stored_attribute'],
            'generationClause' => ['field_def'],
            'statementRoot' => ['query'],
            'statement' => ['statement'],
            'columnName' => ['field_ident', 'ident'],
            'declaredType' => ['type'],
            'expression' => ['expr'],
            'tableElements' => ['table_element_list', 'create_field_list'],
            'createTable' => ['create_table_stmt', 'create'],
            'createHeader' => [],
            'tableName' => ['table_ident'],
            'tableConstraint' => ['table_constraint_def'],
            'columnReference' => ['simple_ident'],
            'identifierToken' => ['IDENT', 'IDENT_QUOTED'],
            'parameterToken' => ['PARAM_MARKER'],
            'projectionList' => ['select_item_list'],
            'projectionExpression' => ['expr', 'table_wild'],
            'projectionAlias' => ['select_alias'],
            'selectStatement' => ['select_stmt', 'select'],
            'selectBody' => ['query_specification'],
            'from' => ['from_clause'],
            'where' => ['where_clause'],
            'selectOptions' => ['select_options'],
            'orderingChildren' => ['expr', 'opt_ordering_direction', 'ordering_direction'],
            'orderingDirection' => ['opt_ordering_direction', 'ordering_direction'],
            'nullsOrder' => [],
            'stringToken' => ['TEXT_STRING'],
            'limit' => ['limit_clause'],
            'offset' => [],
            'paginationExpression' => ['expr', 'limit_option'],
            'selectChildren' => ['from_clause', 'where_clause', 'select_options', 'select_item_list', 'opt_from_clause', 'opt_where_clause'],
            'unsupportedModifier' => ['with_clause', 'into_clause', 'opt_into', 'locking_clause', 'locking_clause_list'],
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
}
