<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\DefinitionCommands;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\ExpressionRule;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\ConflictRule;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\FlagRule;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\LiteralRule;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\NameRule;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\TypeNameRule;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\WindowRule;
use SqlSemantics\Platform\Sqlite\Lowering\Mutation\MutationRule;
use SqlSemantics\Platform\Sqlite\Lowering\Query\FromRule;
use SqlSemantics\Platform\Sqlite\Lowering\Query\QueryCommands;
use SqlSemantics\Platform\Sqlite\Lowering\Query\ResultRule;
use SqlSemantics\Platform\Sqlite\Lowering\Query\SelectRule;
use SqlSemantics\Platform\Sqlite\Lowering\Query\SortRule;
use SqlSemantics\Platform\Sqlite\Lowering\Query\WithRule;
use SqlSemantics\Platform\Sqlite\Lowering\Trigger\TriggerRule;
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
     * @var NameRule The identifier rules: name(nm), token(Token), scoped(nm, dbnm), qualified(fullname), pair(nm, nm), list(idlist), optionalList(idlist_opt), collation(collate)
     */
    public readonly NameRule $names;

    /**
     * @var FlagRule The yes-or-no keyword groups: temporary(temp), ifNotExists(ifnotexists)
     */
    public readonly FlagRule $flags;

    /**
     * @var LiteralRule The literal rules: term(term), number(Token)
     */
    public readonly LiteralRule $literals;

    /**
     * @var TypeNameRule The type name rules: named(typetoken), signed(signed), plusNumber(plus_num), minusNumber(minus_num)
     */
    public readonly TypeNameRule $typeNames;

    /**
     * @var ConflictRule The conflict resolution rules: resolution(resolvetype), raised(raisetype), onConflict(onconf), orConflict(orconf)
     */
    public readonly ConflictRule $conflicts;

    /**
     * @var ExpressionRule The expression rules: expression(expr), term(term), list(exprlist), items(nexprlist), where(where_opt)
     */
    public readonly ExpressionRule $expressions;

    /**
     * @var SortRule The ordering rules: terms(sortlist), orderBy(orderby_opt), direction(sortorder), nulls(nulls), columns(eidlist), optionalColumns(eidlist_opt)
     */
    public readonly SortRule $ordering;

    /**
     * @var SelectRule The query rules: select(select), body(selectnowith), limit(limit_opt)
     */
    public readonly SelectRule $selects;

    /**
     * @var ResultRule The result column rules: columns(selcollist), alias(as), quantifier(distinct), values(values|mvalues)
     */
    public readonly ResultRule $results;

    /**
     * @var FromRule The input relation rules: from(from), terms(seltablist), indexed(indexed_by), optionalIndexed(indexed_opt)
     */
    public readonly FromRule $inputs;

    /**
     * @var WithRule The common table rules: optional(with), clause(wqlist, recursive)
     */
    public readonly WithRule $commonTables;

    /**
     * @var WindowRule The window rules: window(window), definitions(window_clause)
     */
    public readonly WindowRule $windows;

    /**
     * @var MutationRule The data change rules: command(cmd), target(xfullname), assignments(setlist), returning(returning)
     */
    public readonly MutationRule $mutations;

    /**
     * @var TriggerRule The trigger rules: create(trigger_decl, trigger_cmd_list)
     */
    public readonly TriggerRule $triggers;

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
        $this->flags = new FlagRule($this);
        $this->literals = new LiteralRule($this);
        $this->typeNames = new TypeNameRule($this);
        $this->conflicts = new ConflictRule($this);
        $this->expressions = new ExpressionRule($this);
        $this->ordering = new SortRule($this);
        $this->selects = new SelectRule($this);
        $this->results = new ResultRule($this);
        $this->inputs = new FromRule($this);
        $this->commonTables = new WithRule($this);
        $this->windows = new WindowRule($this);
        $this->mutations = new MutationRule($this);
        $this->triggers = new TriggerRule($this);
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
            'ecmd: explain cmdx SEMI' => $this->definitionCommands->explained($form->node(0), $this->command($this->productions->form($form->node(1))->node(0))),
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
