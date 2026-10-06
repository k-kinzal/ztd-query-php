<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Explain;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers EXPLAIN, DESCRIBE, HELP and USE.
 *
 * Rule: MYSQL-EXPLAIN-LOWERING-001. Scope: describe, explanable_command
 * (5.6), explainable_command (5.7), opt_extended_describe (5.x),
 * describe_command, opt_describe_column, describe_stmt, explain_stmt,
 * explainable_stmt, opt_explain_options, opt_explain_format,
 * opt_explain_into, opt_explain_for_schema (8.0 and later), help, use.
 * Constructs: DescribeTable, Explain, ExplainConnection, Help,
 * UseDatabase. The explained statement is lowered by the query and data
 * manipulation families. DESC, DESCRIBE and EXPLAIN are the same keyword
 * (UtilityNoise). Terminates: every part is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html,
 * https://dev.mysql.com/doc/refman/8.4/en/help.html,
 * https://dev.mysql.com/doc/refman/8.4/en/use.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class ExplainRule
{
    /**
     * The 5.x modifiers of EXPLAIN.
     */
    private const MODIFIERS = ['opt_extended_describe: EXTENDED_SYM' => ExplainModifier::Extended, 'opt_extended_describe: PARTITIONS_SYM' => ExplainModifier::Partitions];

    /**
     * The productions of an explained statement, by the position of the statement (the FOR DATABASE clause is before it).
     */
    private const EXPLAINED = [
        'explanable_command: select' => 0, 'explanable_command: insert' => 0, 'explanable_command: replace' => 0, 'explanable_command: update' => 0,
        'explanable_command: delete' => 0, 'explainable_command: select' => 0, 'explainable_command: insert_stmt' => 0,
        'explainable_command: replace_stmt' => 0, 'explainable_command: update_stmt' => 0, 'explainable_command: delete_stmt' => 0,
        'explainable_stmt: select_stmt' => 0, 'explainable_stmt: insert_stmt' => 0, 'explainable_stmt: replace_stmt' => 0,
        'explainable_stmt: update_stmt' => 0, 'explainable_stmt: delete_stmt' => 0, 'explainable_stmt: opt_explain_for_schema select_stmt' => 1,
        'explainable_stmt: opt_explain_for_schema insert_stmt' => 1, 'explainable_stmt: opt_explain_for_schema replace_stmt' => 1,
        'explainable_stmt: opt_explain_for_schema update_stmt' => 1, 'explainable_stmt: opt_explain_for_schema delete_stmt' => 1,
    ];

    /**
     * The explained statement rules the query family lowers; the data manipulation family lowers the others.
     */
    private const QUERIES = ['select' => true, 'select_stmt' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `describe`, `describe_stmt`, `explain_stmt`, `help` or `use`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        switch ($form->signature) {
            case 'help: HELP_SYM ident_or_text':
                return new Help($this->lowering->names->identifier($form->node(1)));
            case 'use: USE_SYM ident':
                return new UseDatabase($this->lowering->names->identifier($form->node(1)));
            case 'describe: describe_command table_ident opt_describe_column':
            case 'describe_stmt: describe_command table_ident opt_describe_column':
                $this->verb($form->node(0));

                return new DescribeTable(new InspectedTable($this->lowering->names->qualified($form->node(1))), $this->column($form->node(2)));
            case 'describe: describe_command opt_extended_describe explanable_command':
            case 'describe: describe_command opt_extended_describe explainable_command':
                $this->verb($form->node(0));

                return $this->legacy($this->lowering->form($form->node(1)), $this->lowering->form($form->node(2)));
            case 'explain_stmt: describe_command opt_explain_options explainable_stmt':
                $this->verb($form->node(0));

                return $this->modern($form->node(1), null, $this->lowering->form($form->node(2)));
            case 'explain_stmt: describe_command opt_explain_options INTO @ ident_or_text explainable_stmt':
                $this->verb($form->node(0));

                return $this->modern($form->node(1), $form->node(4), $this->lowering->form($form->node(5)));
            default:
                throw ImplementationGap::production($form);
        }
    }

    /**
     * Confirms that a node is `describe_command`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function verb(Node $verb): void
    {
        $form = $this->lowering->form($verb);
        if ($form->signature !== 'describe_command: DESC' && $form->signature !== 'describe_command: DESCRIBE') {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the column pattern of DESCRIBE: a node of `opt_describe_column`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function column(Node $column): Name|Text|null
    {
        $form = $this->lowering->form($column);

        return match ($form->signature) {
            'opt_describe_column:' => null,
            'opt_describe_column: text_string' => $this->lowering->literals->text($form->node(0)),
            'opt_describe_column: ident' => $this->lowering->names->identifier($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers EXPLAIN of MySQL 5.x from its `opt_extended_describe` and its explained command.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacy(Form $options, Form $command): Statement
    {
        $format = null;
        $modifier = null;
        if ($options->signature === 'opt_extended_describe: FORMAT_SYM EQ ident_or_text') {
            $format = $this->lowering->names->identifier($options->node(2));
        } elseif ($options->signature !== 'opt_extended_describe:') {
            $modifier = self::MODIFIERS[$options->signature] ?? throw ImplementationGap::production($options);
        }
        if ($command->signature === 'explainable_command: FOR_SYM CONNECTION_SYM real_ulong_num') {
            return new ExplainConnection($this->lowering->numbers->numeral($command->node(2)), $format, false, $modifier);
        }
        return new Explain($this->explained($command), $format, false, $modifier);
    }

    /**
     * Lowers EXPLAIN of MySQL 8.0 and later from its options, the variable of an INTO written before the statement, and its `explainable_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function modern(Node $options, ?Node $into, Form $explainable): Statement
    {
        [$format, $analyze, $target] = (new OptionRule($this->lowering))->options($options);
        $variable = $into === null ? $target : $this->lowering->variables->user($into);
        if ($explainable->signature === 'explainable_stmt: FOR_SYM CONNECTION_SYM real_ulong_num') {
            return new ExplainConnection($this->lowering->numbers->numeral($explainable->node(2)), $format, $analyze, null, $variable);
        }
        $database = (self::EXPLAINED[$explainable->signature] ?? 0) === 1 ? (new OptionRule($this->lowering))->database($explainable->node(0)) : null;

        return new Explain($this->explained($explainable), $format, $analyze, null, $variable, $database);
    }

    /**
     * Lowers the statement of an explained-statement production through the family that owns it.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function explained(Form $form): Statement
    {
        $statement = $form->node(self::EXPLAINED[$form->signature] ?? throw ImplementationGap::production($form));

        return isset(self::QUERIES[$statement->name]) ? $this->lowering->queries->statement($statement) : $this->lowering->dml->statement($statement);
    }
}
