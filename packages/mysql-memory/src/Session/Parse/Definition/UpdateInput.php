<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Program\Write\UpdateUse;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\RefusedUpdate;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Preserves completed input notices before preparing a complete UPDATE template.
 * SQL remains confined to this input boundary.
 *
 * @visibility MySqlMemory
 */
final class UpdateInput
{
    /**
     * Retains a prepared instruction or invokes its complete source request.
     *
     * @throws DeferredPreparation When the target can be prepared
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        \SqlSemantics\Diagnostic\Check::invariant($problem instanceof RefusedUpdate, 'The refusal preserves its complete UPDATE.');
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $notices = (new SyntaxWarnings())->completed($parser, $statement, strlen($statement), $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        foreach ((new ModernWarnings())->before($parser->tokenize($statement), $statement, strlen($statement), $parser) as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $syntax = (new ModernWarnings())->recorded(array_column($notices, 1), StatementError::UnknownError->error(), $session, false);
        if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof ParseFailure) !== []) {
            return $syntax;
        }
        try {
            $use = new UpdateUse();
            if ($session->preparation->inputPrepared) {
                $use->check($problem->write, $session, true);
                throw new DeferredPreparation(new \MySqlMemory\Command\Prepared\PendingUpdate($problem->write, $session->variables->database));
            }
            $use->raise($problem->write, $session, false);
        } catch (SqlError $error) {
            $error = (new \MySqlMemory\Session\Problem\Precision())->legacy($error, $session->settings()->release());
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
                foreach ($error->following as [$code, $message]) {
                    $session->diagnostics->error($code, $message);
                }
            }
            return new SqlError($error->error, $error->getMessage(), $failure, [], $error->signalled, $error->getCode(), true);
        }
    }
}
