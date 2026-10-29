<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;

/**
 * Reads operands that cannot be published as semantic values.
 *
 * A refusal keeps the diagnostic and its completion boundary only. The
 * caller retains earlier input conditions before returning that refusal.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RejectedOperand
{
    /**
     * Answers an operand's typed refusal without retaining the parse node.
     */
    public function of(Node $node, Lowering $lowering): ?RejectedInput
    {
        $initial = $this->initial($node, $lowering);
        if ($initial !== null) {
            return $initial;
        }
        $entry = (new ClientEntry())->generated($node) ?? (new Replication\SyntaxLogMoment())->rejected($node, $lowering->profile->grammar) ?? (new Dml\SyntaxHandlerRead())->rejected($node, $lowering->profile->grammar) ?? (new Leaf\SyntaxTableNames())->rejected($node, $lowering) ?? (new Routine\DeclarationPrefix())->rejected($node, $lowering);
        if ($entry !== null) {
            return $entry;
        }
        $setting = (new Replication\SyntaxSettings())->rejected($node, $lowering);
        if ($setting !== null) {
            return $setting;
        }
        $aborting = $this->completed($node, $lowering);
        if ($aborting !== null) {
            return new RejectedInput($aborting->problem, (new SourceRanges())->span($node)[1] ?? 0, $aborting->aborts);
        }
        foreach ((new Account\SyntaxRoles())->completed($node, $lowering) as $role) {
            if ($role->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch) {
                return new RejectedInput($role->problem, (new SourceRanges())->span($node)[1] ?? 0, $role->aborts);
            }
        }
        $database = $node->name === 'default_encryption' ? null : (new Server\Instance\SyntaxOptions())->rejected($node, $lowering);
        if ($database !== null) {
            return $database;
        }
        return $this->lowered($node, $lowering);
    }

    /**
     * Collects refusals that are owned by the completed production.
     * Fractional declaration operands keep their enclosing declaration boundary.
     */
    public function completed(Node $node, Lowering $lowering): ?\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure
    {
        if (in_array($node->name, ['alter_algorithm_option', 'alter_lock_option'], true)) {
            return null;
        }
        $notice = ($node->name === 'NUM_literal' ? $lowering->numbers->completed($node) : null) ?? (new Query\SyntaxAliases())->completed($node, $lowering) ?? $lowering->tableChanges->completed($node, false) ?? (new Account\SyntaxPrivileges())->completed($node, $lowering);
        return $notice?->problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize || $notice?->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute || $notice?->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand || $notice?->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\InvalidAttribute ? null : $notice;
    }

    /**
     * Refuses a completed LOAD operand before later clauses.
     */
    public function initial(Node $node, Lowering $lowering): ?RejectedInput
    {
        return (new Dml\SyntaxLoadInput())->rejected($node, $lowering);
    }

    /**
     * Runs ordinary operand lowering for constructors that can refuse input.
     */
    public function lowered(Node $node, Lowering $lowering): ?RejectedInput
    {
        if (in_array($node->name, ['alter', 'alter_event_stmt'], true)) {
            return (new Routine\EventRule($lowering))->rejected($node);
        }
        try {
            if ($node->name === 'key_part') {
                return (new TableDefinition\Key\SyntaxPrefixes())->rejected($node, $lowering);
            }
            if ($node->name === 'bool_pri') {
                return (new Expression\ComparisonRule())->rejected($node, $lowering);
            }
            if ($node->name === 'function_call_generic') {
                (new Call\FunctionRule($lowering))->call($lowering->form($node));
            } elseif ($node->name === 'partition_entry') {
                return (new ClientEntry())->partition($node->tokens()[0] ?? null, $lowering->profile);
            }
            if (in_array($node->name, ['user', 'role'], true)) {
                $lowering->users->account($node);
            } elseif (in_array($node->name, ['sp_cond', 'sqlstate'], true)) {
                (new Routine\Condition\ConditionRule($lowering))->value($node);
            } elseif ($node->name === 'factor') {
                (new Account\IdentificationRule($lowering))->factor($node);
            } elseif ($node->name === 'alter_user' && str_contains($lowering->form($node)->signature, ' factor')) {
                (new Account\UserListRule($lowering, new Account\IdentificationRule($lowering)))->alter($node);
            }
        } catch (RejectedInput $failure) {
            return $failure;
        }

        return (new Query\Legacy\DerivedAliases())->rejected($node, $lowering);
    }
}
