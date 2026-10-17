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
     * Formats immediate or deferred target contradictions using their semantic operands.
     */
    public function placement(\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege|\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel $problem, Session $session): ?SqlError
    {
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel) {
            return $this->level($problem, $session);
        }
        return match ($problem->error) {
            'ER_UNSUPPORTED_USE_OF_GRANT_AS' => \MySqlMemory\Error\Family\AccountError::UnsupportedGrantAs->error(),
            'ER_ILLEGAL_GRANT_FOR_TABLE' => \MySqlMemory\Error\Family\AccountError::IllegalGrantForTable->error(),
            default => null,
        };
    }

    /**
     * Preserves account spelling, database and AS precedence before a target condition.
     */
    public function level(\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel $problem, Session $session): SqlError
    {
        if ($problem->definition) {
            return \MySqlMemory\Error\Family\AccountError::IllegalGrantForTable->error();
        }
        $request = $problem->request;
        $names = new \MySqlMemory\Command\Account\Names($session->settings()->release());
        try {
            if (!$session->settings()->legacy() || !$request->columnLevel()) {
                $names->check([...$names->users($request->grantees), $request->as?->user, ...$request->as->roles->roles ?? []]);
            }
            (new \MySqlMemory\Command\Account\Levels($session->settings()->release()))->target($request->kind, $request->level, $session);
        } catch (SqlError $error) {
            return $error;
        }
        if ($request->as !== null && !$request->level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel) {
            return \MySqlMemory\Error\Family\AccountError::UnsupportedGrantAs->error();
        }
        if (!$session->settings()->legacy() || !$request->columnLevel()) {
            foreach ($request->grantees as $grantee) {
                $names->resolve($names->identity($grantee->user, $session), $session->diagnostics);
            }
        }
        return $problem->condition->error === 'ER_ILLEGAL_PRIVILEGE_LEVEL'
            ? \MySqlMemory\Error\Family\AccountError::IllegalPrivilegeLevel->error($request->level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel ? $request->level->name->name->value : strtoupper($problem->condition->privilege))
            : \MySqlMemory\Error\Family\AccountError::IllegalGrantForTable->error();
    }

    /**
     * Resolves a completed column target before reading any recipient operands.
     */
    public function target(\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel $problem, Session $session): SqlError
    {
        return $problem->level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel && $problem->program?->schema === null && $session->variables->database === ''
            ? QueryError::NoDatabase->error()
            : \MySqlMemory\Error\Family\AccountError::IllegalGrantForTable->error();
    }

    /**
     * Formats a column privilege's routine-kind refusal at the next input token.
     */
    public function columns(\SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege $problem, Session $session): SqlError
    {
        $level = $problem->level;
        if (($level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel || ($level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel && $level->name->schema === null)) && $session->variables->database === '') {
            return QueryError::NoDatabase->error();
        }
        if ($session->settings()->legacy() && !$level instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel) {
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
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $boundary, $semantics->profile(), $session->resolution()->replicaNetTimeout);

        return (new ModernWarnings())->recorded(array_column($notices, 1), QueryError::NoDatabase->error(), $session);
    }
}
