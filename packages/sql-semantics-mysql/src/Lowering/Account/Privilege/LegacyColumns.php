<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account\Privilege;

use SqlParser\Parser\Node;
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

}
