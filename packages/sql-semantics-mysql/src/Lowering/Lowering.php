<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Account\AccountRules;
use SqlSemantics\Platform\MySql\Lowering\Call\CallRules;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\DefinitionRoutes;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\DefinitionTails;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\Family;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\OptimizerHints;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\StatementRoutes;
use SqlSemantics\Platform\MySql\Lowering\Dml\DmlRules;
use SqlSemantics\Platform\MySql\Lowering\Expression\ExpressionRules;
use SqlSemantics\Platform\MySql\Lowering\Leaf\CharsetRule;
use SqlSemantics\Platform\MySql\Lowering\Leaf\LiteralRule;
use SqlSemantics\Platform\MySql\Lowering\Leaf\NameRule;
use SqlSemantics\Platform\MySql\Lowering\Leaf\NumberRule;
use SqlSemantics\Platform\MySql\Lowering\Leaf\OptionRule;
use SqlSemantics\Platform\MySql\Lowering\Leaf\UserRule;
use SqlSemantics\Platform\MySql\Lowering\Leaf\VariableRule;
use SqlSemantics\Platform\MySql\Lowering\Query\QueryRules;
use SqlSemantics\Platform\MySql\Lowering\Replication\ReplicationRules;
use SqlSemantics\Platform\MySql\Lowering\Routine\RoutineRules;
use SqlSemantics\Platform\MySql\Lowering\Server\ServerRules;
use SqlSemantics\Platform\MySql\Lowering\TableChange\TableChangeRules;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\TableDefinitionRules;
use SqlSemantics\Platform\MySql\Lowering\Type\TypeRule;
use SqlSemantics\Platform\MySql\Lowering\Utility\UtilityRules;
use SqlSemantics\Statement\Statement;

/**
 * The lowering of one MySQL parse tree: the statement dispatcher and the entry rules of every family.
 *
 * Rule: MYSQL-INPUT-001. Scope: start_entry, sql_statement,
 * simple_statement_or_begin, simple_statement, opt_end_of_input (8.0 and
 * later); query, verb_clause, statement (5.6, 5.7); and the routing of
 * create, alter and drop. One input holds at most one statement; the empty
 * input holds none. A statement is handed to the family that owns its rule
 * (MYSQL-STATEMENT-ROUTES-001, MYSQL-DEFINITION-ROUTES-001). The other
 * start_entry alternatives begin with a grammar selector token the lexer
 * never produces, so no SQL text reaches them. An optimizer hint comment is
 * reported as a missing rule (MYSQL-OPTIMIZER-HINTS-001). Terminates: the
 * root rules are unit productions over strict subtrees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Lowering
{
    /**
     * The start_entry alternatives the server enters by a grammar selector token; no SQL text parses to them.
     */
    private const SELECTED = [
        'start_entry: GRAMMAR_SELECTOR_EXPR bit_expr END_OF_INPUT' => true, 'start_entry: GRAMMAR_SELECTOR_PART partition_clause END_OF_INPUT' => true,
        'start_entry: GRAMMAR_SELECTOR_GCOL IDENT_sys ( expr ) END_OF_INPUT' => true, 'start_entry: GRAMMAR_SELECTOR_CTE table_subquery END_OF_INPUT' => true,
        'start_entry: GRAMMAR_SELECTOR_DERIVED_EXPR expr END_OF_INPUT' => true,
    ];

    /**
     * The root productions that hold one statement at their first position.
     */
    private const TERMINATED = [
        'sql_statement: simple_statement_or_begin ; opt_end_of_input' => true, 'sql_statement: simple_statement_or_begin END_OF_INPUT' => true,
        'query: verb_clause ; opt_end_of_input' => true, 'query: verb_clause END_OF_INPUT' => true,
    ];

    /**
     * @var NameRule The identifier rules
     */
    public readonly NameRule $names;

    /**
     * @var LiteralRule The literal rules
     */
    public readonly LiteralRule $literals;

    /**
     * @var NumberRule The rules of numbers at positions that are not expressions
     */
    public readonly NumberRule $numbers;

    /**
     * @var VariableRule The user and system variable rules
     */
    public readonly VariableRule $variables;

    /**
     * @var OptionRule The rules of shared optional keywords and markers
     */
    public readonly OptionRule $options;

    /**
     * @var CharsetRule The character set and collation name rules
     */
    public readonly CharsetRule $charsets;

    /**
     * @var UserRule The account name rules
     */
    public readonly UserRule $users;

    /**
     * @var TypeRule The data type rules
     */
    public readonly TypeRule $types;

    /**
     * @var ExpressionRules The entry rules of expressions
     */
    public readonly ExpressionRules $expressions;

    /**
     * @var CallRules The entry rules of function-like expressions
     */
    public readonly CallRules $calls;

    /**
     * @var QueryRules The entry rules of queries
     */
    public readonly QueryRules $queries;

    /**
     * @var DmlRules The entry rules of data manipulation statements
     */
    public readonly DmlRules $dml;

    /**
     * @var TableDefinitionRules The entry rules of table, index and view definitions
     */
    public readonly TableDefinitionRules $tableDefinitions;

    /**
     * @var TableChangeRules The entry rules of ALTER TABLE, partitioning, DROP, RENAME and TRUNCATE
     */
    public readonly TableChangeRules $tableChanges;

    /**
     * @var RoutineRules The entry rules of stored programs
     */
    public readonly RoutineRules $routines;

    /**
     * @var AccountRules The entry rules of accounts and privileges
     */
    public readonly AccountRules $accounts;

    /**
     * @var ReplicationRules The entry rules of replication statements
     */
    public readonly ReplicationRules $replication;

    /**
     * @var ServerRules The entry rules of server administration and storage objects
     */
    public readonly ServerRules $server;

    /**
     * @var UtilityRules The entry rules of SET, SHOW, EXPLAIN and utility statements
     */
    public readonly UtilityRules $utility;

    /**
     * @param Productions $productions The productions of the grammar release
     * @param Leaves $leaves The record of operand leaves of this analysis
     * @param LanguageProfile $profile The language profile the tree was parsed under
     */
    public function __construct(public readonly Productions $productions, public readonly Leaves $leaves, public readonly LanguageProfile $profile)
    {
        $this->names = new NameRule($this);
        $this->literals = new LiteralRule($this);
        $this->numbers = new NumberRule($this);
        $this->variables = new VariableRule($this);
        $this->options = new OptionRule($this);
        $this->charsets = new CharsetRule($this);
        $this->users = new UserRule($this);
        $this->types = new TypeRule($this);
        $this->expressions = new ExpressionRules($this);
        $this->calls = new CallRules($this);
        $this->queries = new QueryRules($this);
        $this->dml = new DmlRules($this);
        $this->tableDefinitions = new TableDefinitionRules($this);
        $this->tableChanges = new TableChangeRules($this);
        $this->routines = new RoutineRules($this);
        $this->accounts = new AccountRules($this);
        $this->replication = new ReplicationRules($this);
        $this->server = new ServerRules($this);
        $this->utility = new UtilityRules($this);
    }

    /**
     * Lowers a complete input into the statements it holds: one, or none for an empty input.
     *
     * @return list<Statement>
     * @throws ImplementationGap When a production has no rule or the input holds an optimizer hint comment
     */
    public function statements(Node $input): array
    {
        $hint = $this->profile->grammar === GrammarRelease::MySql5651 ? null : (new OptimizerHints())->first($input);
        if ($hint !== null) {
            throw ImplementationGap::rule('MySQL optimizer hints, which the parser delivers as a comment: ' . $hint);
        }
        $form = $this->productions->form($input);
        Check::invariant(!isset(self::SELECTED[$form->signature]), 'A grammar selector entry is not reachable from SQL text: ' . $form->signature);
        if ($form->signature === 'start_entry: sql_statement') {
            $form = $this->productions->form($form->node(0));
        }
        if ($form->signature === 'sql_statement: END_OF_INPUT' || $form->signature === 'query: END_OF_INPUT') {
            return [];
        }
        if (!isset(self::TERMINATED[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        if (count($form->node->children) === 3) {
            $end = $this->productions->form($form->node(2));
            Check::invariant($end->signature === 'opt_end_of_input:' || $end->signature === 'opt_end_of_input: END_OF_INPUT', 'A statement ends the input.');
        }

        return [$this->statement($form->node(0))];
    }

    /**
     * Lowers one statement: a node of simple_statement_or_begin, simple_statement, verb_clause or statement.
     *
     * Compound statements, EXPLAIN and prepared statement sources reach the
     * statement they contain through this method.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->productions->form($statement);
        if ($form->signature === 'simple_statement_or_begin: simple_statement' || $form->signature === 'verb_clause: statement') {
            $form = $this->productions->form($form->node(0));
        }
        if ($form->signature === 'simple_statement_or_begin: begin_stmt' || $form->signature === 'verb_clause: begin') {
            return $this->server->statement($form->node(0));
        }
        $family = (new StatementRoutes())->family($form->signature) ?? throw ImplementationGap::production($form);

        return $family === Family::Definition ? $this->definition($form->node(0)) : $this->routed($family, $form->node(0));
    }

    /**
     * Lowers a node of create, alter or drop through the family its production is routed to.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definition(Node $definition): Statement
    {
        $form = $this->productions->form($definition);
        $family = (new DefinitionRoutes())->family($form->signature) ?? throw ImplementationGap::production($form);
        if ($form->signature === 'create: CREATE view_or_trigger_or_sp_or_event') {
            return (new DefinitionTails($this))->create($form->node(1));
        }

        return match ($family) {
            Family::Account => $this->accounts->definition($form),
            Family::Routine => $this->routines->definition($form),
            Family::Server => $this->server->definition($form),
            Family::TableChange => $this->tableChanges->definition($form),
            Family::TableDefinition => $this->tableDefinitions->definition($form),
            Family::Definition, Family::Dml, Family::Query, Family::Replication, Family::Utility => throw ImplementationGap::production($form),
        };
    }

    /**
     * Hands the node of a statement rule to the family that owns the rule.
     */
    public function routed(Family $family, Node $statement): Statement
    {
        return match ($family) {
            Family::Account => $this->accounts->statement($statement),
            Family::Definition => $this->definition($statement),
            Family::Dml => $this->dml->statement($statement),
            Family::Query => $this->queries->statement($statement),
            Family::Replication => $this->replication->statement($statement),
            Family::Routine => $this->routines->statement($statement),
            Family::Server => $this->server->statement($statement),
            Family::TableChange => $this->tableChanges->statement($statement),
            Family::TableDefinition => $this->tableDefinitions->statement($statement),
            Family::Utility => $this->utility->statement($statement),
        };
    }

    /**
     * Answers the typed view of the production a node matched.
     */
    public function form(Node $node): Form
    {
        return $this->productions->form($node);
    }
}
