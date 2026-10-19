<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies explicit session fixtures for missing prepared, savepoint and XA state.
 *
 * These steps use identifiers from the original object. They neither repair that
 * object nor classify its next outcome as valid: every step and the repeated
 * object must still be observed against MySQL. Unrecognized failures get no
 * automatic explanation.
 */
final class SessionHistory
{
    /**
     * @return list<Statement> Setup operations before repeating the original object
     */
    public function steps(Statement|Scalar $node, int $code): array
    {
        if ($code === 1243 && ($node instanceof Execute || $node instanceof Deallocate)) {
            $parameters = $node instanceof Execute ? count($node->variables) : 0;
            $source = 'SELECT ' . ($parameters === 0 ? '1' : implode(', ', array_fill(0, $parameters, '?')));
            return [new Prepare($node->name, new Text($source))];
        }
        if ($code === 1305 && ($node instanceof ReleaseSavepoint || $node instanceof RollbackToSavepoint)) {
            return [new StartTransaction(), new Savepoint($node->savepoint)];
        }
        if (!in_array($code, [1397, 1399], true)) {
            return [];
        }
        return match (true) {
            $node instanceof XaEnd => [new XaStart($node->xid)],
            $node instanceof XaPrepare, $node instanceof XaRollback => [new XaStart($node->xid), new XaEnd($node->xid)],
            $node instanceof XaCommit => [new XaStart($node->xid), new XaEnd($node->xid), ...($node->onePhase ? [] : [new XaPrepare($node->xid)])],
            default => [],
        };
    }
}
