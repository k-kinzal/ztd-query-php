<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;

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
        $names = (new Utility\Set\SyntaxNames())->assignment($node, $lowering);
        if ($names !== null) {
            return $names;
        }
        $entry = (new ClientEntry())->generated($node);
        if ($entry !== null) {
            return $entry;
        }
        $setting = (new Replication\SyntaxSettings())->completed($node, $lowering);
        if ($setting?->problem instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber) {
            return new RejectedInput($setting->problem, (new SourceRanges())->span($node)[1] ?? 0, $setting->aborts);
        }
        $aborting = $lowering->tableChanges->completed($node) ?? (new Account\SyntaxPrivileges())->completed($node, $lowering);
        if ($aborting !== null) {
            return new RejectedInput($aborting->problem, (new SourceRanges())->span($node)[1] ?? 0, true);
        }
        $roles = (new Account\SyntaxRoles())->completed($node, $lowering);
        if ($roles !== []) {
            return new RejectedInput($roles[0]->problem, (new SourceRanges())->span($node)[1] ?? 0, $roles[0]->aborts);
        }
        $database = (new Server\Instance\SyntaxOptions())->rejected($node, $lowering);
        if ($database !== null) {
            return $database;
        }
        if ($node->name === 'simple_expr' && $lowering->form($node)->signature === 'simple_expr: CAST_SYM ( expr AT_SYM LOCAL_SYM AS cast_type opt_array_cast )') {
            return new RejectedInput(new NotSupportedYet('AT LOCAL'), (new SourceRanges())->span($node)[1] ?? 0, false);
        }
        try {
            if ($node->name === 'partition_entry') {
                return (new ClientEntry())->partition($node->tokens()[0] ?? null, $lowering->profile);
            }
            if (in_array($node->name, ['sp_cond', 'sqlstate'], true)) {
                (new Routine\Condition\ConditionRule($lowering))->value($node);
            } elseif ($node->name === 'factor') {
                (new Account\IdentificationRule($lowering))->factor($node);
            } elseif ($node->name === 'alter_user' && str_contains($lowering->form($node)->signature, ' factor')) {
                (new Account\UserListRule($lowering, new Account\IdentificationRule($lowering)))->alter($node);
            }
        } catch (RejectedInput $failure) {
            return $failure;
        }

        return null;
    }
}
