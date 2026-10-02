<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\CreateTableRule;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\DefinitionCommands;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\ExpressionRule;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\NameRule;
use SqlSemantics\Platform\Sqlite\Lowering\Query\QueryCommands;
use SqlSemantics\Platform\Sqlite\Lowering\Query\SelectRule;
use SqlSemantics\Statement\Statement;

/**
 * The lowering of one SQLite parse tree: the entry rule and the rule objects of one analysis.
 *
 * Rule: SQLITE-INPUT-001. Scope: input, cmdlist, ecmd, cmdx, cmd. An input is
 * the ordered list of its commands; an empty command contributes nothing.
 * Terminates: the command list is flattened iteratively and each command is a
 * strict subtree. Source: https://sqlite.org/lang.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Lowering
{
    /**
     * @var NameRule The identifier rules
     */
    public readonly NameRule $names;

    /**
     * @var ExpressionRule The expression rules
     */
    public readonly ExpressionRule $expressions;

    /**
     * @var SelectRule The query rules
     */
    public readonly SelectRule $selects;

    /**
     * @var CreateTableRule The table definition rules
     */
    public readonly CreateTableRule $tables;

    /**
     * @var QueryCommands The commands that read or write rows
     */
    public readonly QueryCommands $queryCommands;

    /**
     * @var DefinitionCommands The commands that define, change or administer the database
     */
    public readonly DefinitionCommands $definitionCommands;

    /**
     * @param Productions $productions The productions of the grammar release
     * @param Leaves $leaves The record of operand leaves of this analysis
     */
    public function __construct(public readonly Productions $productions, public readonly Leaves $leaves)
    {
        $this->names = new NameRule($this);
        $this->expressions = new ExpressionRule($this);
        $this->selects = new SelectRule($this);
        $this->tables = new CreateTableRule($this);
        $this->queryCommands = new QueryCommands($this);
        $this->definitionCommands = new DefinitionCommands($this);
    }

    /**
     * Lowers a complete input into its statements.
     *
     * @return list<Statement>
     */
    public function statements(Node $input): array
    {
        $form = $this->productions->form($input);
        $statements = [];
        foreach ((new Lists())->items($form->node(0)) as $command) {
            $statement = $this->terminated($command);
            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * Lowers one terminated command; an empty command is no statement.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function terminated(Node $command): ?Statement
    {
        $form = $this->productions->form($command);

        return match ($form->signature) {
            'ecmd: SEMI' => null,
            'ecmd: cmdx SEMI' => $this->command($this->productions->form($form->node(0))->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers one command: the commands that read or write rows first, every other command otherwise.
     */
    public function command(Node $command): Statement
    {
        $form = $this->productions->form($command);

        return $this->queryCommands->command($form) ?? $this->definitionCommands->command($form);
    }
}
