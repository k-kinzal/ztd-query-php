<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account\Privilege;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Account\PrivilegeRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Interprets legacy column targets at their completion boundary.
 *
 * GRANT and REVOKE share this restriction. Completed reductions allow an
 * unfinished recipient list to preserve the earlier target refusal.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/grant.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LegacyColumns
{
    private ?StaticPrivilege $column = null;

    /**
     * Observes completed operands without retaining parser nodes.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return null;
        }
        if ($node->name === 'clear_privileges') {
            $this->column = null;
        } elseif ($node->name === 'grant_privileges') {
            $this->column = null;
            foreach ((new PrivilegeRule($lowering))->privileges($node) as $privilege) {
                if ($privilege instanceof StaticPrivilege && $privilege->columns !== []) {
                    $this->column = $privilege;
                    break;
                }
            }
        } elseif ($node->name === 'grant_ident' && $this->column !== null) {
            $level = (new PrivilegeRule($lowering))->level($node);
            if (!$level instanceof ObjectLevel) {
                return new ParseFailure(new RefusedColumnLevel($this->column, $level), true);
            }
        }
        return null;
    }

    /**
     * Reads the same prefix from a completed command before its recipients.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if (!in_array($node->name, ['grant_command', 'revoke_command'], true)) {
            return null;
        }
        foreach ($node->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            $notice = $this->completed($child, $lowering);
            if ($notice !== null) {
                return new RejectedInput($notice->problem, (new SourceRanges())->span($child)[1] ?? 0, true);
            }
        }
        return null;
    }

    /**
     * Rejects the completed target before lowering any later operands.
     *
     * @throws RejectedInput When a column privilege has no table target
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege> $items The already interpreted privilege list
     */
    public function requireTable(array $items, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel|\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel|\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel|ObjectLevel $level, int $end): void
    {
        if ($level instanceof ObjectLevel) {
            return;
        }
        foreach ($items as $item) {
            if ($item instanceof StaticPrivilege && $item->columns !== []) {
                throw new RejectedInput(new RefusedColumnLevel($item, $level), $end, true);
            }
        }
    }

    /**
     * Attaches the enclosing declaration's database to a parsed refusal.
     */
    public function scoped(Node $tree, RejectedInput $failure, Lowering $lowering): RejectedInput
    {
        if (!$failure->problem instanceof RefusedColumnLevel) {
            return $failure;
        }
        foreach ($tree->find('sp_name') as $name) {
            if (((new SourceRanges())->span($name)[1] ?? PHP_INT_MAX) < $failure->end) {
                $problem = $failure->problem;
                return new RejectedInput(new RefusedColumnLevel($problem->privilege, $problem->level, $lowering->names->qualified($name)), $failure->end, $failure->aborts);
            }
        }
        return $failure;
    }
}
