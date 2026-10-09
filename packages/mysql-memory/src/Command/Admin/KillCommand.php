<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;

/**
 * Evaluates KILL's process identifier and ends the named connection or its active statement.
 *
 * Identifiers are integers narrowed to 32 bits; NULL is zero. KILL QUERY leaves an idle
 * connection open, while KILL CONNECTION rolls its transaction back. Verified on MySQL 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/kill.html.
 *
 * @visibility MySqlMemory
 */
final class KillCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Evaluates the identifier and signals the target session.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof Kill);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        try {
            $operand = $planner->compiler->compile($statement->process, new Scope());
        } catch (SqlError $error) {
            throw self::preparation($error, $statement);
        }
        $value = $operand->evaluate(new Frame($context));
        $id = ($value !== null && $operand->domain()->kind === Kind::Decimal
            ? Convert::decimalInteger((string) $value, $context, false)
            : (int) Convert::toInteger($value, $operand->domain()->withQuiet(false), $context)) & 0xffffffff;
        $target = ($session->instance->sessions[$id] ?? null)?->get();
        if ($target === null || $target->released) {
            throw AdministrationError::NoSuchThread->error($id);
        }
        if ($target->transaction->statements->running !== [] || $target === $session) {
            $target->interrupted = true;
        }
        if ($statement->scope !== KillScope::Query) {
            $target->release();
        }
        if ($target === $session) {
            throw StatementError::QueryInterrupted->error();
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Appends the condition the server records after a process expression fails preparation.
     */
    public static function preparation(SqlError $error, Node $statement): SqlError
    {
        if (!$statement instanceof Kill) {
            return $error;
        }

        return new SqlError($error->error, $error->getMessage(), $error->getPrevious(), [...$error->following, [StatementError::SetConstantExpression->value, StatementError::SetConstantExpression->message()]], $error->signalled, null, $error->recorded);
    }
}
