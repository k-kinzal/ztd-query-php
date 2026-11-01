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
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Node;

/**
 * Evaluates KILL's process identifier and ends the named connection or its active statement.
 *
 * Identifiers are integers narrowed to 32 bits from MySQL 5.7 on; NULL is zero. KILL QUERY leaves an idle
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof Kill);
        $process = $this->process($statement, $session);
        $planner = new Planner($statement, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        try {
            $operand = $planner->compiler->compile($process, new Scope());
        } catch (SqlError $error) {
            throw self::preparation($error, $statement);
        }
        $id = $this->identifier($operand, $context, $session->settings()->release());
        $target = ($session->instance->sessions[$id] ?? null)?->get();
        if ($target === null || $target->released) {
            throw AdministrationError::NoSuchThread->error(\MySqlMemory\Value\Integer::text($id, true));
        }
        if ($target->transaction->statements->running !== [] || $target === $session) {
            $target->interrupted = true;
        }
        if ($statement->scope !== KillScope::Query) {
            $target->close();
        }
        if ($target === $session) {
            throw StatementError::QueryInterrupted->error();
        }

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Validates syntax-time dependencies while deferring the process expression to EXECUTE.
     *
     * @throws SqlError When a variable, function spelling or character-set condition prevents preparation
     */
    public function prepare(Kill $statement, Session $session): void
    {
        (new \MySqlMemory\Session\Problem\Reading())->parsed($statement, $session);
        foreach ($statement->statementInformation()->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem)) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
            }
        }
    }

    /**
     * Prepares a retained process operand before statement access or identifier evaluation.
     *
     * @throws SqlError When the operand has a scalar-use or dependency condition
     */
    public function process(Kill $statement, Session $session): \SqlSemantics\Statement\Scalar
    {
        foreach ($statement->statementInformation()->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet && $problem->feature === Kill::DEPENDENCIES) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
            }
        }
        if (!$statement->process instanceof \SqlSemantics\Statement\Scalar) {
            throw (new \MySqlMemory\Program\Expression\KillUse())->error($statement->process, $session);
        }
        return $statement->process;
    }

    /**
     * Evaluates an identifier with the release's conversion and failure behavior.
     * MySQL 5.6 keeps all 64 bits and records an unknown zero thread after an evaluation failure;
     * later releases narrow identifiers to 32 bits and stop at the evaluation failure.
     *
     * @throws SqlError When evaluating the identifier fails
     */
    public function identifier(\MySqlMemory\Evaluation\Evaluable $operand, Context $context, GrammarRelease $release): int
    {
        try {
            $value = $operand->evaluate(new Frame($context));
        } catch (SqlError $error) {
            if ($release !== GrammarRelease::MySql5651) {
                throw $error;
            }

            throw new SqlError($error->error, $error->getMessage(), $error->getPrevious(), [...$error->following, [AdministrationError::NoSuchThread->value, AdministrationError::NoSuchThread->message(0)]], $error->signalled, null, $error->recorded);
        }
        $domain = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? $operand->domain() : $operand->domain()->withQuiet(false);
        $id = $value !== null && $domain->kind === Kind::Decimal
            ? Convert::decimalInteger((string) $value, $context, false)
            : (int) Convert::toInteger($value, $domain, $context);

        return $release === GrammarRelease::MySql5651 ? $id : ($id & 0xffffffff);
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
