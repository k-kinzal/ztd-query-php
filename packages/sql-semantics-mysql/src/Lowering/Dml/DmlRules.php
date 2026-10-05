<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Dml\Insert\InsertRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\FileFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\TextFileFormat;
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
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->form($statement);

        return match ($statement->name) {
            'insert_stmt', 'replace_stmt' => (new InsertRule($this->lowering))->statement($form),
            'insert', 'replace' => (new InsertRule($this->lowering))->legacy($form),
            'update', 'update_stmt' => (new ChangeRule($this->lowering))->update($form),
            'delete_stmt' => (new ChangeRule($this->lowering))->delete($form),
            'delete' => (new ChangeRule($this->lowering))->legacyDelete($form),
            'load', 'load_stmt' => (new LoadRule($this->lowering))->statement($form),
            'handler', 'handler_stmt' => (new HandlerRule($this->lowering))->statement($form),
            'do', 'do_stmt' => (new InvocationRule($this->lowering))->evaluation($form),
            'call', 'call_stmt' => (new InvocationRule($this->lowering))->call($form),
            'import_stmt' => (new InvocationRule($this->lowering))->import($form),
            'prepare', 'execute', 'deallocate' => (new InvocationRule($this->lowering))->prepared($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers REPLACE or IGNORE before a query that fills a table: a node of `opt_duplicate` or
     * `duplicate`; neither is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function duplicateHandling(Node $duplicate): ?DuplicateHandling
    {
        return (new TargetRule($this->lowering))->duplicates($duplicate);
    }

    /**
     * Lowers the text file format of INTO OUTFILE: the nodes of `opt_load_data_charset`, `opt_field_term`
     * and `opt_line_term`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function fileFormat(Node $charset, Node $fields, Node $lines): FileFormat
    {
        $format = new FormatRule($this->lowering);

        return new TextFileFormat($format->charset($charset), $format->fields($fields), $format->lines($lines));
    }

    /**
     * Lowers the table names of a multiple-table DELETE or of a locking clause: a node of
     * `table_alias_ref_list`.
     *
     * @return list<QualifiedName>
     * @throws ImplementationGap When a production has no rule
     */
    public function deleteTargets(Node $list): array
    {
        return (new TargetRule($this->lowering))->tables($list);
    }

    /**
     * Tells for each table of a node of `table_alias_ref_list` whether it is written with `.*`.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>
     * @throws ImplementationGap When a production has no rule
     */
    public function wildcards(Node $list): array
    {
        return (new TargetRule($this->lowering))->wildcards($list);
    }

    /**
     * Lowers the values of one row, each an expression or DEFAULT: a node of `opt_values` or `values`; an
     * absent list is empty.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When a production has no rule
     */
    public function rowValues(Node $values): array
    {
        return (new ValueRule($this->lowering))->row($values);
    }
}
