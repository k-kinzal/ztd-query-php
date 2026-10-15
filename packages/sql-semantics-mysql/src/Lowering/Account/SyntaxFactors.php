<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Retains authentication-factor refusals at their grammar completion boundary.
 *
 * Factor operands stop before later malformed input. A completed factor
 * pair stops before the next account. Earlier account-name errors keep
 * their precedence. No invalid factor or parser node enters the model.
 * Observed on native MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxFactors
{
    /**
     * Answers a completed factor, pair or preceding account-name refusal.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        try {
            if (in_array($node->name, ['user', 'role'], true)) {
                $lowering->users->account($node);
                return null;
            }
            if (in_array($lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                return null;
            }
            if ($node->name === 'factor') {
                (new IdentificationRule($lowering))->factor($node);
            } elseif ($node->name === 'alter_user' && str_contains($lowering->form($node)->signature, ' factor')) {
                (new UserListRule($lowering, new IdentificationRule($lowering)))->alter($node);

            }
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, $failure->aborts);
        }

        return null;
    }

}
