<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Warning;

/**
 * Retains conditions read inside SHOW PARSE_TREE before its release-build refusal.
 *
 * The semantic notices distinguish an aborting operand from a continuing error.
 * Binding and execution diagnostics of the inspected statement are not reached.
 * No SQL text or parse tree is needed here. Observed on MySQL 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class DebugRequest
{
    /**
     * Records completed semantic notices followed by the debug-only syntax refusal.
     */
    public function refusal(MySqlStatement $operation, SqlError $syntax, Session $session): SqlError
    {
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree && \MySqlMemory\Command\Dispatcher::program($operation->statement)) {
            try {
                (new \MySqlMemory\Session\Access\DefaultDatabase())->check($operation->statement, $session);
            } catch (SqlError $header) {
                return (new \MySqlMemory\Session\CacheOptions())->recorded([], null, $header, $session);
            }
        }
        $problem = $this->operand($operation);
        if ($problem !== null) {
            $syntax = (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
        } elseif ($this->preserves($operation)) {
            return (new \MySqlMemory\Session\CacheOptions())->recorded([], null, $syntax, $session, clear: false);
        }
        return $this->notices($operation->statementInformation()->warnings, $syntax, $session);
    }

    /**
     * Finds name bindings performed by grammar actions before the debug-build check.
     * Column references, SELECT INTO and LIMIT bindings are contextualized later.
     */
    public function operand(MySqlStatement $operation): ?Diagnostic
    {
        foreach ($operation->statementInformation()->diagnostics as $problem) {
            if (($problem instanceof ProgramProblem && in_array($problem->rule, [ProgramRule::UndeclaredVariable, ProgramRule::UndefinedCondition], true)) || ($problem instanceof UnknownSystemVariable && $problem->assigned)) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Keeps the previous diagnostic area for a valid GET DIAGNOSTICS inspection.
     * Its undeclared local target instead starts a new area before reporting the error.
     */
    public function preserves(MySqlStatement $operation): bool
    {
        return $operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree
            && $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics
            && $this->operand($operation) === null;
    }

    /**
     * Retains syntax-stage notices before the debug-only entry refusal.
     *
     * @param list<Warning> $warnings Completed semantic notices
     */
    public function notices(array $warnings, SqlError $syntax, Session $session): SqlError
    {
        $notices = array_values(array_filter($warnings, static fn (Warning $warning): bool => ($warning instanceof Deprecation || ($warning instanceof ParseFailure && $warning->phase === NoticePhase::Syntax)) && !Stages::afterReading($warning)));

        return (new ModernWarnings())->recorded($notices, $syntax, $session);
    }
}
