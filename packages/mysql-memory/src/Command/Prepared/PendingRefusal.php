<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\RejectedInput;

/**
 * A prepared statement whose reading refusal the server reports only when it executes.
 *
 * PREPARE parses the statement and defers the checks the server runs when the
 * command executes: a GRANT or REVOKE privilege outside its level is accepted
 * by PREPARE and refused by EXECUTE (verified on 8.4.7: `PREPARE s FROM 'REVOKE
 * ALL ON PROCEDURE * FROM u'` succeeds and EXECUTE answers 1144).
 *
 * @visibility MySqlMemory
 */
final class PendingRefusal implements Pending
{
    /**
     * @param string $text The prepared statement text
     * @param RejectedInput $failure The refusal the reading raised after the whole statement was read
     */
    public function __construct(public readonly string $text, public readonly RejectedInput $failure)
    {
    }

    /**
     * Exposes no markers: the refusal is raised before any marker is bound.
     */
    public function parameters(): ParameterBindings
    {
        return new ParameterBindings([]);
    }

    /**
     * Reports the retained refusal with the notices the server records before it.
     * GRANT and REVOKE commit the open transaction before their level is checked (the
     * rows written before the failing EXECUTE survive a later ROLLBACK, verified on 8.4.7).
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters The protocol bindings
     * @throws SqlError Always: the retained refusal
     */
    public function execute(Session $session, string $label, array $parameters): Reply
    {
        $session->activity->begin();
        \MySqlMemory\Session\State\StatementCounters::received($session);
        $session->text = $label;
        $session->diagnostics->clear();
        $session->transaction->commit();
        $tree = $session->semantics()->parser()->parse($this->text);

        throw (new \MySqlMemory\Session\Parse\Reader())->operand($this->failure, $tree, $this->text, $session);
    }
}
