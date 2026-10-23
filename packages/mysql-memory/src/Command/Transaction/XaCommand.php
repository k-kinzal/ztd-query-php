<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Transaction;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Registry\PreparedBranch;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\XaState;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Operation;

/**
 * Executes the XA statements: XA START, XA END, XA PREPARE, XA COMMIT, XA ROLLBACK and XA RECOVER.
 *
 * JOIN, RESUME and SUSPEND are refused first (XAER_INVAL). XA START opens the XA transaction of
 * the session, unless another is active or idle (XAER_RMFAIL), a plain transaction is open
 * (XAER_OUTSIDE) or the XID is prepared already (XAER_DUPID). XA END makes it idle and XA PREPARE
 * retains ownership on legacy releases or when xa_detach_on_prepare is OFF. XA COMMIT ends a prepared branch, or the idle one of the session
 * with ONE PHASE; XA ROLLBACK ends either. While the session's transaction is active or idle,
 * another XID is XAER_RMFAIL for XA COMMIT and XA ROLLBACK, and XAER_NOTA for XA END and
 * XA PREPARE; an XID the statement does not find otherwise is XAER_NOTA. XA RECOVER lists the
 * prepared branches of every session: the format id, the lengths of the two parts of the XID and
 * its bytes, written in hexadecimal after CONVERT XID.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html,
 * https://dev.mysql.com/doc/refman/8.4/en/xa-states.html.
 *
 * @visibility MySqlMemory
 */
final class XaCommand implements Command
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
     * Moves the XA transaction of the session, or a prepared branch, to its next state.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if ($statement instanceof XaRecover) {
            return $this->recover($statement, $session, $context);
        }
        match (true) {
            $statement instanceof XaStart => $this->start($statement, $session),
            $statement instanceof XaEnd => $this->end($statement, $session),
            $statement instanceof XaPrepare => $this->prepare($statement->xid, $session),
            $statement instanceof XaCommit => $this->finish($statement->xid, $session, true, $statement->onePhase),
            $statement instanceof XaRollback => $this->finish($statement->xid, $session, false, false),
            default => null,
        };

        return new Completion();
    }

    /**
     * Opens the XA transaction of the session.
     *
     * @throws \MySqlMemory\Error\SqlError When the session cannot start it
     */
    public function start(XaStart $statement, Session $session): void
    {
        if ($statement->option !== null) {
            throw StatementError::XaInvalidArguments->error();
        }
        $transaction = $session->transaction;
        $transaction->guard();
        if ($transaction->active()) {
            throw StatementError::XaWorkOutside->error();
        }
        $transaction->end();
        $key = $this->key($statement->xid);
        if (isset($session->instance->registry->prepared[$key])) {
            throw StatementError::XaDuplicateXid->error();
        }
        $transaction->begin();
        $transaction->xa = XaState::Active;
        $transaction->xid = $key;
        [$transaction->xidFormat, $global, $branch] = $this->parts($statement->xid);
        $transaction->legacyXid = strlen($global) . ':' . $global . $branch;
    }

    /**
     * Makes the active XA transaction of the session idle.
     *
     * @throws \MySqlMemory\Error\SqlError When the session has no active XA transaction of the XID
     */
    public function end(XaEnd $statement, Session $session): void
    {
        if ($statement->option !== null) {
            throw StatementError::XaInvalidArguments->error();
        }
        $transaction = $session->transaction;
        if ($transaction->xa !== XaState::Active) {
            throw StatementError::XaWrongState->error($transaction->xa->value);
        }
        if (!$this->same($statement->xid, $session)) {
            throw StatementError::XaUnknownXid->error();
        }
        $transaction->xa = XaState::Idle;
    }

    /**
     * Prepares the idle XA transaction, retaining ownership when detachment is disabled.
     *
     * @throws \MySqlMemory\Error\SqlError When the session has no idle XA transaction of the XID
     */
    public function prepare(Xid $xid, Session $session): void
    {
        $transaction = $session->transaction;
        if ($transaction->xa !== XaState::Idle) {
            throw StatementError::XaWrongState->error($transaction->xa->value);
        }
        $key = $transaction->xid;
        assert($key !== null);
        if (!$this->same($xid, $session)) {
            throw StatementError::XaUnknownXid->error();
        }
        [, $global, $branch] = $this->parts($xid);
        $format = $transaction->xidFormat;
        $detached = !$session->settings()->legacy() && in_array(strtoupper((string) $session->variables->read('xa_detach_on_prepare')), ['ON', '1'], true);
        $session->instance->registry->prepared[$key] = new PreparedBranch($format, $global, $branch, $transaction->detach(), $detached ? null : $session->id);
        if (!$detached) {
            $transaction->xa = XaState::Prepared;
            $transaction->xid = $key;
            $transaction->xidFormat = $format;
            $transaction->open = $transaction->explicit = true;
        }
    }

    /**
     * Commits or rolls back the idle XA transaction of the session or a prepared branch.
     *
     * @throws \MySqlMemory\Error\SqlError When neither is found in a state the statement ends
     */
    public function finish(Xid $xid, Session $session, bool $commit, bool $onePhase): void
    {
        $transaction = $session->transaction;
        $key = $this->key($xid);
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 && !isset($session->instance->registry->prepared[$key])) {
            if (!$this->same($xid, $session)) {
                throw StatementError::XaUnknownXid->error();
            }
            if ($transaction->xa === XaState::NonExisting) {
                throw StatementError::XaWrongState->error($transaction->xa->value);
            }
        }
        if ($transaction->xa === XaState::Prepared) {
            $this->finishOwned($xid, $session, $commit, $onePhase);
            return;
        }
        if ($transaction->xa === XaState::Active) {
            throw StatementError::XaWrongState->error($transaction->xa->value);
        }
        if ($transaction->xa === XaState::Idle) {
            if (!$this->same($xid, $session) || ($commit && !$onePhase)) {
                throw StatementError::XaWrongState->error($transaction->xa->value);
            }
            if (!$commit) {
                $transaction->restore();
            }
            $transaction->end();

            return;
        }
        if ($transaction->active()) {
            throw StatementError::XaWrongState->error(XaState::NonExisting->value);
        }
        $this->finishDetached($xid, $session, $commit);
    }

    /**
     * Completes the prepared branch still owned by this session.
     */
    public function finishOwned(Xid $xid, Session $session, bool $commit, bool $onePhase): void
    {
        $transaction = $session->transaction;
        if (!$this->same($xid, $session) || ($commit && $onePhase)) {
            throw StatementError::XaWrongState->error(XaState::Prepared->value);
        }
        $key = $transaction->xid;
        assert($key !== null);
        $branch = $session->instance->registry->prepared[$key];
        $transaction->end();
        if ($commit) {
            $session->instance->transactions->apply($branch->changes, (int) floor($session->variables->clock()));
        }
        unset($session->instance->registry->prepared[$key]);
    }

    /**
     * Completes a detached branch, leaving a branch owned by another live session untouched.
     * MySQL 5.7 matches disconnected branch data without the format id; a different format
     * removes its recovery entry without committing rows. Verified through two connections.
     */
    public function finishDetached(Xid $xid, Session $session, bool $commit): void
    {
        [$format, $global, $part] = $this->parts($xid);
        $registry = $session->instance->registry;
        $key = $this->key($xid);
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            foreach ($registry->prepared as $candidate => $branch) {
                if ($branch->transaction === $global && $branch->branch === $part) {
                    $key = $candidate;
                    break;
                }
            }
        }
        $branch = $registry->prepared[$key] ?? throw StatementError::XaUnknownXid->error();
        $owner = $branch->owner === null ? null : ($session->instance->sessions[$branch->owner] ?? null)?->get();
        if ($owner !== null && !$owner->released) {
            throw StatementError::XaUnknownXid->error();
        }
        if ($commit && $branch->format === $format) {
            $session->instance->transactions->apply($branch->changes, (int) floor($session->variables->clock()));
        }
        unset($registry->prepared[$key]);
    }

    /**
     * MySQL 5.6 compares branch data without its format id and retains the last identity.
     */
    public function same(Xid $xid, Session $session): bool
    {
        if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return $session->transaction->xid === $this->key($xid);
        }
        [, $global, $branch] = $this->parts($xid);
        return $session->transaction->legacyXid === strlen($global) . ':' . $global . $branch;
    }

    /**
     * Lists the prepared branches; MySQL 5.6 and 5.7 report numbers 11 characters long, and 5.6 data of 128 characters (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function recover(XaRecover $statement, Session $session, Context $context): Reply
    {
        $number = ColumnFlag::NotNull->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value;
        $width = $session->settings()->legacy() ? 11 : 12;
        $headings = [
            new Heading('formatID', Field::LongLong, $width, $number),
            new Heading('gtrid_length', Field::LongLong, $width, $number),
            new Heading('bqual_length', Field::LongLong, $width, $number),
            Heading::text('data', Field::VarString, $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? 128 : 258, ColumnFlag::NotNull->value, 31),
        ];
        $rows = [];
        foreach ($session->instance->registry->prepared as $branch) {
            $data = $branch->transaction . $branch->branch;
            $rows[] = [$branch->format, strlen($branch->transaction), strlen($branch->branch), $statement->convertXid ? '0x' . strtoupper(bin2hex($data)) : $data];
        }

        return (new Listing($headings))->sent($rows, $context);
    }

    /**
     * Answers the format id, the global transaction id and the branch qualifier of an XID; the format id is 1 when the XID names none.
     *
     * @return array{int, string, string}
     */
    public function parts(Xid $xid): array
    {
        $literals = new Literals();

        return [$xid->format === null ? 1 : (int) $literals->number($xid->format), $literals->bytes($xid->transaction), $xid->branch === null ? '' : $literals->bytes($xid->branch)];
    }

    /**
     * Answers the key of an XID.
     */
    public function key(Xid $xid): string
    {
        [$format, $global, $branch] = $this->parts($xid);

        return PreparedBranch::key($format, $global, $branch);
    }
}
