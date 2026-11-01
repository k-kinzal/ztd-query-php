<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Formats an XA input refusal at the position supplied by semantic lowering.
 *
 * SQL is consulted only for the error excerpt, never to execute an identifier.
 *
 * @visibility MySqlMemory
 */
final class XaInput
{
    /**
     * Quotes the explicit format operand or the token following an identifier.
     */
    public function error(RefusedXaIdentifier $problem, Session $session): SqlError
    {
        $offset = $problem->offset;
        if ($problem->following) {
            $tokens = (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($session->semantics()->parser(), $session->text);
            foreach ($tokens as $token) {
                if ($token->offset >= $offset) {
                    $offset = $token->offset;
                    break;
                }
            }
        }

        return (new Syntax(Charset::named((string) $session->variables->read('character_set_client'))))->near($offset, $session->text, $session->following, new RuntimeException($problem->message()), $session->settings()->legacy(), $session->semantics()->parser());
    }
}
