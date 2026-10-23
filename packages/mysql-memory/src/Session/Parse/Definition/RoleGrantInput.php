<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Command\Account\Role\Grants;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Preserves completed input notices before preparing a complete role grant template.
 * SQL remains confined to this input boundary.
 *
 * @visibility MySqlMemory
 */
final class RoleGrantInput
{
    /**
     * Retains a prepared instruction or invokes its complete source request.
     *
     * @throws DeferredPreparation When the target can be prepared
     * @throws CompletedTemplate When invocation returns a complete reply
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        \SqlSemantics\Diagnostic\Check::invariant($problem instanceof RefusedGrantRoles, 'The refusal preserves its complete role grant.');
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $notices = (new SyntaxWarnings())->completed($parser, $statement, strlen($statement), $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        foreach ((new ModernWarnings())->before($parser->tokenize($statement), $statement, strlen($statement), $parser) as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $syntax = (new ModernWarnings())->recorded(array_column($notices, 1), AccountError::IllegalGrantForTable->error(), $session, false);
        if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof ParseFailure) !== []) {
            return $syntax;
        }
        try {
            $use = new Grants();
            $use->header($problem->request, $session);
            if ($session->preparation->inputPrepared) {
                throw new DeferredPreparation(new \MySqlMemory\Command\Prepared\PendingRoleGrant($problem->request, $session->variables->database));
            }
            throw new CompletedTemplate((new \MySqlMemory\Program\Account\RoleGrantUse())->run($problem->request, $session, false));
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            return new SqlError($error->error, $error->getMessage(), $failure, [], $error->signalled, $error->getCode(), true);
        }
    }
}
