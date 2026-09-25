<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Parser\SqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;

/**
 * Assembles PostgreSql semantic behavior from independent core contracts.
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
        return new PostgreSqlParser($version);
    }

    /**
     * Supplies the default declaration namespace.
     */
    public function defaultSchema(): string
    {
        return 'public';
    }

    /**
     * @return array{string, string}
     */
    public function statementNames(): array
    {
        return ['parse_toplevel', 'stmt'];
    }

    /**
     * Maps the supported grammar productions onto semantic roles.
     */
    public function syntax(): Policy\SyntaxRules
    {
        return new Policy\SyntaxRules([
            'columnName' => ['ColId'],
            'declaredType' => ['Typename'],
            'expression' => ['a_expr'],
            'createTable' => ['CreateStmt'],
            'createHeader' => [],
            'tableName' => ['qualified_name'],
            'tableConstraint' => ['TableConstraint'],
            'columnReference' => ['columnref'],
            'identifierToken' => ['IDENT'],
            'parameterToken' => ['PARAM'],
            'projectionList' => [],
            'projectionExpression' => ['a_expr'],
            'projectionAlias' => ['ColLabel', 'BareColLabel'],
            'selectStatement' => ['SelectStmt'],
            'selectBody' => ['simple_select'],
            'from' => ['from_clause'],
            'where' => ['where_clause'],
            'selectOptions' => ['distinct_clause'],
            'orderingChildren' => ['a_expr', 'opt_asc_desc', 'opt_nulls_order'],
            'orderingDirection' => ['opt_asc_desc'],
            'nullsOrder' => ['opt_nulls_order'],
            'stringToken' => ['SCONST', 'USCONST'],
            'limit' => ['limit_clause'],
            'offset' => ['offset_clause'],
            'paginationExpression' => ['a_expr'],
            'selectChildren' => ['opt_target_list', 'target_list', 'distinct_clause', 'from_clause', 'where_clause'],
            'unsupportedModifier' => ['opt_for_locking_clause', 'for_locking_clause', 'with_clause', 'into_clause'],
            'relation' => ['table_ref'],
            'qualifiedExpression' => [],
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
