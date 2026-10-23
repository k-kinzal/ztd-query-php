<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\User\RefusedRenameUser;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Preserves input notices before retaining or reaching an account identity request.
 * Original SQL is used only at this input boundary.
 *
 * @visibility MySqlMemory
 */
final class RenameInput
{
    /**
     * Keeps preparation independent of the account catalog and of eventual pair failures.
     *
     * @throws DeferredPreparation When preparation retains the complete instruction
     * @throws CompletedTemplate When the complete instruction returns a reply
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        Check::invariant($problem instanceof RefusedRenameUser, 'The refusal retains its complete ordered renamings.');
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $statement, strlen($statement), $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        foreach ((new ModernWarnings())->before($parser->tokenize($statement), $statement, strlen($statement), $parser) as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $names = new Names($session->settings()->release());
        if ($notices === [] && (new \MySqlMemory\Session\Execution($session))->retains($problem->request, new \MySqlMemory\Command\Account\RenameUserCommand())) {
            $session->diagnostics->retain();
        } else {
            $syntax = (new ModernWarnings())->recorded(array_column($notices, 1), $names->failure('RENAME USER', ''), $session, false);
            if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof ParseFailure) !== []) {
                return $syntax;
            }
        }
        try {
            foreach ($problem->request->renamings as $pair) {
                $names->check([$pair->from, $pair->to]);
            }
            if ($session->preparation->inputPrepared) {
                throw new DeferredPreparation(new \MySqlMemory\Command\Prepared\PendingRenameUser($problem->request, $session->variables->database));
            }
            throw new CompletedTemplate((new \MySqlMemory\Program\Account\RenameUse())->run($problem->request, $session, false));
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
                foreach ($error->following as [$code, $message]) {
                    $session->diagnostics->error($code, $message);
                }
            }
            return new SqlError($error->error, $error->getMessage(), $failure, $error->following, $error->signalled, $error->getCode(), true);
        }
    }
}
