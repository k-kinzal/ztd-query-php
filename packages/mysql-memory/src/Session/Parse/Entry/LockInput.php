<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Access\LockNames;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\SyntaxLocks;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Statement\Operation;

/**
 * Resolves completed lock specifications before the original input error.
 * The shared consumer sees semantic locks and facts, never the partial parser tree.
 *
 * @visibility MySqlMemory
 */
final class LockInput
{
    /**
     * Preserves completed lock conditions when script splitting reports the syntax refusal.
     */
    public function reported(SqlError $error, string $statement, Session $session): SqlError
    {
        $end = (new \MySqlMemory\Session\CacheOptions())->cause($error)?->token->offset ?? strlen($statement);
        $earlier = $this->before($statement, $end, $session);
        if ($earlier !== null) {
            return $earlier;
        }
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        if ((new SyntaxLocks())->before($parser, $statement, $end, $semantics->profile()) === []) {
            return $error;
        }
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $semantics->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);

        return (new ModernWarnings())->recorded(array_column($notices, 1), $error, $session);
    }

    /**
     * Answers an earlier lock-name error and preserves only notices reached before it.
     */
    public function before(string $statement, int $end, Session $session): ?SqlError
    {
        $semantics = $session->semantics();
        $parser = $semantics->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        try {
            $locks = (new SyntaxLocks())->before($parser, $statement, $end, $semantics->profile());
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            return (new \MySqlMemory\Session\Parse\Reader())->rejection($failure, $statement, $session);
        }
        $prefix = [];
        foreach ($locks as [$boundary, $lock]) {
            $prefix[] = $lock;
            $context = $semantics->context(searchPath: $session->variables->database === '' ? null : new \SqlSemantics\Contract\SearchPath($session->variables->database));
            $failure = (new LockNames())->failure(new Operation($context, new LockTables($prefix)), $session);
            if ($failure !== null) {
                $notices = (new SyntaxWarnings())->completed($parser, $statement, $boundary, $semantics->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);

                return (new ModernWarnings())->recorded(array_column($notices, 1), $failure[1], $session);
            }
        }

        return null;
    }
}
