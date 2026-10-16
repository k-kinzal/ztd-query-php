<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use RuntimeException;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\Account\SyntaxPrivileges;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;

/**
 * Resolves the connection database required by a completed privilege target.
 *
 * Target interpretation belongs to SQL Semantics. Input is retained only by
 * this boundary to collect conditions preceding the target's completion.
 *
 * @visibility MySqlMemory
 */
final class PrivilegeInput
{
    /**
     * Formats an immediate GRANT AS placement refusal, leaving other privilege rules to binding.
     */
    public function placement(\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege $problem): ?SqlError
    {
        return $problem->error === 'ER_UNSUPPORTED_USE_OF_GRANT_AS' ? \MySqlMemory\Error\Family\AccountError::UnsupportedGrantAs->error() : null;
    }

    /**
     * Formats a column privilege's routine-kind refusal at the next input token.
     */
    public function columns(\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege $problem, Session $session): SqlError
    {
        if ($session->settings()->legacy() && !$problem->object) {
            return \MySqlMemory\Error\Family\AccountError::IllegalGrantForTable->error();
        }
        $parser = $session->semantics()->parser();
        $offset = strlen($session->text);
        $tokens = (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($parser, $session->text);
        foreach ($tokens as $index => $token) {
            if ($token->offset >= $problem->offset) {
                $offset = $token->text === ';' && in_array($tokens[$index + 1]->name ?? null, ['END_OF_INPUT', '$end'], true) ? strlen($session->text) : $token->offset;
                break;
            }
        }

        return (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near($offset, $session->text, $session->following, new RuntimeException($problem->message()), $session->settings()->legacy(), $parser);
    }

    /**
     * Reports a missing database before recipient-name and privilege-list failures.
     */
    public function database(string $statement, int $end, Session $session): ?SqlError
    {
        if ($session->variables->database !== '' || $session->settings()->legacy()) {
            return null;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $boundary = (new SyntaxPrivileges())->database($parser, $statement, $end, $semantics->profile());
        if ($boundary === null) {
            return null;
        }
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $boundary, $semantics->profile());

        return (new ModernWarnings())->recorded(array_column($notices, 1), QueryError::NoDatabase->error(), $session);
    }
}
