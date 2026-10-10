<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use Closure;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CloseCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Iterate;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * Runs the statements of a stored program: blocks with their declarations, flow control, cursors, and SQL statements.
 *
 * Each statement runs in turn. A block declares its variables, conditions, cursors and handlers
 * in order and leaves them at its end, closing its cursors. IF and CASE run the statements of
 * the first branch whose condition is true; a CASE without a matching branch nor ELSE is
 * ER_SP_CASE_NOT_FOUND. LOOP repeats until a LEAVE, WHILE tests its condition before each pass
 * and REPEAT after; ITERATE starts the labeled loop again and LEAVE ends the labeled block or
 * loop. A condition a statement raises goes to the handlers in scope (Handlers); a CONTINUE
 * handler for an error in the condition of IF, CASE or a loop goes on after the whole
 * statement. An assignment that sets only variables of the program leaves ROW_COUNT() as it
 * was (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-statements.html,
 * https://dev.mysql.com/doc/refman/8.4/en/begin-end.html.
 *
 * @visibility MySqlMemory
 */
final class Interpreter
{
    /**
     * Runs the SQL statements and evaluates the expressions of the program.
     */
    public readonly Statements $statements;

    /**
     * Handles the conditions the statements raise.
     */
    public readonly Handlers $handlers;

    /**
     * @param Session $session The session the program runs in
     * @param Activation $activation The running program
     */
    public function __construct(public readonly Session $session, public readonly Activation $activation)
    {
        $this->statements = new Statements($session, $activation);
        $this->handlers = new Handlers($this);
    }

    /**
     * Runs statements in order and answers the jump that ends them early, if any.
     *
     * @param list<Node> $statements
     *
     * @throws SqlError When a statement raises an error no handler handles
     */
    public function sequence(array $statements): ?Jump
    {
        foreach ($statements as $statement) {
            $jump = $this->statement($statement);
            if ($jump !== null) {
                return $jump;
            }
        }

        return null;
    }

    /**
     * Runs one statement and answers where control goes instead of the next statement, if anywhere.
     *
     * @throws SqlError When the statement raises an error no handler handles
     */
    public function statement(Node $statement): ?Jump
    {
        return match (true) {
            $statement instanceof Block => $this->block($statement),
            $statement instanceof IfStatement => $this->branches($statement->branches, $statement->otherwise, null, false),
            $statement instanceof SearchedCase => $this->branches($statement->branches, $statement->otherwise, null, true),
            $statement instanceof SimpleCase => $this->branches($statement->branches, $statement->otherwise, $statement->operand, true),
            $statement instanceof Loop, $statement instanceof WhileLoop, $statement instanceof RepeatLoop => $this->loop($statement),
            $statement instanceof Leave => new Jump(Flow::Leave, $statement->label->value),
            $statement instanceof Iterate => new Jump(Flow::Iterate, $statement->label->value),
            $statement instanceof ReturnStatement => $this->returned($statement->value),
            $statement instanceof OpenCursor, $statement instanceof FetchCursor, $statement instanceof CloseCursor => $this->cursor($statement),
            $statement instanceof Signal && $statement->condition instanceof ConditionName => $this->sql($this->named($statement)),
            $statement instanceof Resignal && $statement->condition instanceof ConditionName => $this->sql($this->named($statement)),
            $statement instanceof Statement => $this->sql($statement),
            default => throw \MySqlMemory\Error\Family\StatementError::NotSupportedYet->error('the stored program statement ' . $statement::class),
        };
    }

    /**
     * Answers a SIGNAL or RESIGNAL of a declared condition as one of the SQLSTATE the condition stands for.
     *
     * @throws SqlError When no condition of the name is in scope, or it stands for an error number (ER_SIGNAL_BAD_CONDITION_TYPE)
     */
    public function named(Signal|Resignal $statement): Signal|Resignal
    {
        $name = $statement->condition instanceof ConditionName ? $statement->condition->name->value : '';
        $declared = null;
        foreach ($this->activation->conditions as $condition) {
            $declared = strcasecmp($condition->name->value, $name) === 0 ? $condition : $declared;
        }
        if ($declared === null) {
            throw ProgramError::UndefinedCondition->error($name);
        }
        if (!$declared->value instanceof SqlState) {
            throw ProgramError::SignalConditionKind->error();
        }

        return $statement instanceof Signal ? new Signal($declared->value, $statement->items) : new Resignal($declared->value, $statement->items);
    }

    /**
     * Runs OPEN, FETCH or CLOSE of a cursor and hands the conditions it raises to the handlers.
     *
     * @throws SqlError When the statement raises an error no handler handles
     */
    public function cursor(OpenCursor|FetchCursor|CloseCursor $statement): ?Jump
    {
        $jump = $this->instruction(fn () => (new Cursors($this->statements))->run($statement));

        return $jump?->flow === Flow::Resume ? null : $jump;
    }

    /**
     * Runs an SQL statement of the program and hands the conditions it raises to the handlers.
     *
     * @throws SqlError When the statement raises an error no handler handles
     */
    public function sql(Statement $statement): ?Jump
    {
        $count = $this->session->variables->rowCount;
        $jump = $this->instruction(function () use ($statement): void {
            $this->statements->run($statement);
        });
        if ($statement instanceof SetVariables && $this->local($statement)) {
            $this->session->variables->rowCount = $count;
        }

        return $jump?->flow === Flow::Resume ? null : $jump;
    }

    /**
     * Tells whether a SET statement assigns only variables of the program: parameters, local variables and columns of the row of a trigger.
     */
    public function local(SetVariables $statement): bool
    {
        foreach ($statement->items as $item) {
            if (!$item instanceof NameAssignment || $item->scope !== null) {
                return false;
            }
            $found = $item->qualifier === null ? $this->activation->variable($item->name->value) : $this->activation->row($item->qualifier->value);
            if ($found === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runs one step of the program and hands the conditions it raises to the handlers: an error ends the step, a warning is handled once the step is done.
     *
     * An error a CONTINUE handler handled answers the jump Flow::Resume: control goes on after
     * the statement the step belongs to.
     *
     * @param Closure(): void $step
     *
     * @throws SqlError When the step raises an error no handler handles
     */
    public function instruction(Closure $step): ?Jump
    {
        try {
            $step();
        } catch (SqlError $error) {
            return $this->handlers->failed($error) ?? new Jump(Flow::Resume);
        }

        return $this->handlers->warned();
    }

    /**
     * Runs a block: its declarations, then its statements, and leaves its declarations.
     *
     * @throws SqlError When a statement raises an error no handler handles
     */
    public function block(Block $block): ?Jump
    {
        $mark = $this->activation->mark();
        try {
            $jump = $this->declarations($block) ?? $this->sequence($block->statements);
        } finally {
            $this->activation->leave($mark);
        }

        return $jump === null || $jump->ends($block->label?->value, $block) ? null : $jump;
    }

    /**
     * Declares the variables, conditions, cursors and handlers of a block in order; a variable holds its DEFAULT value, else NULL.
     *
     * @throws SqlError When a DEFAULT value raises an error no handler handles
     */
    public function declarations(Block $block): ?Jump
    {
        $base = count($this->activation->handlers);
        foreach ($block->declarations as $declaration) {
            if ($declaration instanceof VariableDeclaration) {
                $row = new Row($declaration, []);
                $jump = $this->instruction(fn () => $this->declare($declaration, $row));
                $this->activation->scope[] = $row;
                if ($jump !== null && $jump->flow !== Flow::Resume) {
                    return $jump;
                }
            } elseif ($declaration instanceof ConditionDeclaration) {
                $this->activation->conditions[] = $declaration;
            } elseif ($declaration instanceof CursorDeclaration) {
                $this->activation->cursors[] = new Cursor($declaration);
            } elseif ($declaration instanceof HandlerDeclaration) {
                $mark = $this->activation->mark();
                $this->activation->handlers[] = new Handler($declaration, $block, [$mark[0], $base, $mark[2], $mark[3]]);
            }
        }

        return null;
    }

    /**
     * Gives the variables of a declaration their type and their DEFAULT value.
     *
     * @throws SqlError When the DEFAULT value is refused
     */
    public function declare(VariableDeclaration $declaration, Row $row): void
    {
        $domain = VariableDomain::declared($declaration->type, $declaration->collation, $this->activation->collation);
        foreach ($declaration->names as $name) {
            $row->variables[] = new Variable($name->value, $domain);
        }
        $default = $declaration->default;
        if ($default === null) {
            return;
        }
        foreach ($row->variables as $variable) {
            $this->statements->value($default, $variable);
        }
    }

    /**
     * Runs the first branch whose condition is true, else the ELSE statements; with an operand, a branch is taken when the operand equals its value.
     *
     * @param list<ConditionalBranch> $branches
     * @param list<Node> $otherwise
     * @param bool $exhaustive Whether a CASE statement without a matching branch nor ELSE is an error
     *
     * @throws SqlError When a statement raises an error no handler handles
     */
    public function branches(array $branches, array $otherwise, ?Scalar $operand, bool $exhaustive): ?Jump
    {
        foreach ($branches as $branch) {
            [$taken, $jump] = $this->test($operand === null ? $branch->condition : new Comparison(ComparisonOperator::Equal, $operand, $branch->condition));
            if ($taken === null) {
                return $jump;
            }
            if ($taken) {
                return $this->sequence($branch->statements);
            }
        }
        if ($otherwise === [] && $exhaustive) {
            $jump = $this->instruction(static fn () => throw ProgramError::CaseNotFound->error());

            return $jump?->flow === Flow::Resume ? null : $jump;
        }

        return $this->sequence($otherwise);
    }

    /**
     * Runs a loop until its condition stops it or a jump leaves it.
     *
     * @throws SqlError When a statement raises an error no handler handles
     */
    public function loop(Loop|WhileLoop|RepeatLoop $loop): ?Jump
    {
        $label = $loop->label?->value;
        while (true) {
            if ($loop instanceof WhileLoop) {
                [$go, $jump] = $this->test($loop->condition);
                if ($go !== true) {
                    return $jump;
                }
            }
            $jump = $this->sequence($loop->statements);
            if ($jump !== null && !$jump->repeats($label)) {
                return $jump->ends($label, null) ? null : $jump;
            }
            if ($jump === null && $loop instanceof RepeatLoop) {
                [$until, $jump] = $this->test($loop->condition);
                if ($until !== false) {
                    return $jump;
                }
            }
        }
    }

    /**
     * Tests a condition of IF, CASE or a loop: answers whether it is true, or null with the jump a handler makes when it raises a condition that ends the statement.
     *
     * A CONTINUE handler of an error in the condition goes on after the whole statement.
     *
     * @return array{bool|null, Jump|null}
     *
     * @throws SqlError When the condition raises an error no handler handles
     */
    public function test(Scalar $condition): array
    {
        $true = false;
        $jump = $this->instruction(function () use ($condition, &$true): void {
            $true = $this->statements->truth($condition);
        });

        return $jump === null ? [$true, null] : [null, $jump->flow === Flow::Resume ? null : $jump];
    }

    /**
     * Evaluates the value RETURN answers and ends the function with it.
     *
     * @throws SqlError When the value raises an error no handler handles
     */
    public function returned(Scalar $value): ?Jump
    {
        $result = null;
        $jump = $this->instruction(function () use ($value, &$result): void {
            $result = $this->statements->value($value, null, true);
        });
        if ($jump !== null || $result === null) {
            return $jump?->flow === Flow::Resume ? null : $jump;
        }

        return new Jump(Flow::Return, null, null, $result[0], $result[1]);
    }
}
