<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\FileFormat;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the dml family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-DML-ENTRY-001. Scope: INSERT, REPLACE, UPDATE, DELETE, LOAD, DO, HANDLER, CALL, prepared
 * statements and IMPORT TABLE.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-data-manipulation-statements.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DmlRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a data manipulation statement: a node of one of the statement rules this family owns.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function statement(Node $statement): Statement
    {
        throw ImplementationGap::rule('MySQL dml family: statement');
    }

    /**
     * Lowers REPLACE or IGNORE before a query that fills a table: a node of `opt_duplicate` or
     * `duplicate`; neither is null.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function duplicateHandling(Node $duplicate): ?DuplicateHandling
    {
        throw ImplementationGap::rule('MySQL dml family: duplicateHandling');
    }

    /**
     * Lowers the text file format of INTO OUTFILE: the nodes of `opt_load_data_charset`, `opt_field_term`
     * and `opt_line_term`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function fileFormat(Node $charset, Node $fields, Node $lines): FileFormat
    {
        throw ImplementationGap::rule('MySQL dml family: fileFormat');
    }

    /**
     * Lowers the table names of a multiple-table DELETE or of a locking clause: a node of
     * `table_alias_ref_list`.
     *
     * @return list<QualifiedName>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function deleteTargets(Node $list): array
    {
        throw ImplementationGap::rule('MySQL dml family: deleteTargets');
    }

    /**
     * Lowers the values of one row, each an expression or DEFAULT: a node of `opt_values` or `values`; an
     * absent list is empty.
     *
     * @return list<Scalar>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function rowValues(Node $values): array
    {
        throw ImplementationGap::rule('MySQL dml family: rowValues');
    }
}
