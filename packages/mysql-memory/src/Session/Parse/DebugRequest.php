<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Operation;

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
    public function refusal(Operation $operation, SqlError $syntax, Session $session): SqlError
    {
        $notices = array_values(array_filter($operation->facts->warnings, static fn (Warning $warning): bool => ($warning instanceof Deprecation || ($warning instanceof ParseFailure && $warning->phase === NoticePhase::Syntax)) && !Stages::afterReading($warning)));

        return (new ModernWarnings())->recorded($notices, $syntax, $session);
    }
}
