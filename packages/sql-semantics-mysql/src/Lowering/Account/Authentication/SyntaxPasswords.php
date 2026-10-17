<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account\Authentication;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash;

/**
 * Recognizes the legacy ALTER USER authentication restriction at its input boundary.
 *
 * The shared account grammar also serves CREATE USER and GRANT, where the
 * hash form remains valid. Only the current command role survives reductions;
 * no parser object or source string enters a semantic value.
 * Observed through SQL on MySQL 5.7.44, including incomplete statements.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxPasswords
{
    private bool $altering = false;

    /**
     * @param list<Token> $tokens The temporary input tokens
     */
    public function starting(Node $node, Token $lookahead, Lowering $lowering, array $tokens): ?RejectedInput
    {
        if ($lowering->profile->grammar !== GrammarRelease::MySql5744) {
            return null;
        }
        if ($node->name === 'alter_user_command' || $node->name === 'alter') {
            $this->altering = $node->name === 'alter_user_command';
        }
        if (!$this->altering || $node->name !== 'user' || $lookahead->name !== 'IDENTIFIED_SYM') {
            return null;
        }
        $following = array_values(array_filter($tokens, static fn (Token $token): bool => $token->offset >= $lookahead->offset));
        if (array_column(array_slice($following, 0, 4), 'name') !== ['IDENTIFIED_SYM', 'BY', 'PASSWORD', 'TEXT_STRING']) {
            return null;
        }
        try {
            $lowering->users->account($node);
        } catch (RejectedInput $failure) {
            return $failure;
        }

        return new RejectedInput(new RejectedPasswordHash(), $following[3]->offset);
    }
}
