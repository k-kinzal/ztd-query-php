<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The GTID functions and the functions that wait for a replica: GTID_SUBSET, GTID_SUBTRACT, WAIT_FOR_EXECUTED_GTID_SET, SOURCE_POS_WAIT, MASTER_POS_WAIT and WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS.
 *
 * GTID_SUBSET and GTID_SUBTRACT compute on the sets their arguments write (GtidSet); a malformed
 * set is ER_MALFORMED_GTID_SET_SPECIFICATION quoting at most 200 bytes of it. The emulator is
 * neither a source nor a replica, with GTID_MODE OFF, so the waits answer as such a server does:
 * WAIT_FOR_EXECUTED_GTID_SET refuses a NULL set, then a NULL or negative timeout, then GTID_MODE
 * OFF; SOURCE_POS_WAIT answers NULL for a NULL or empty file name, refuses a negative timeout,
 * answers NULL for a named channel and refuses a call without a channel, as no single channel
 * exists (MySQL 8.0 answers NULL); WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS answers NULL, refusing a
 * negative timeout from 5.7 and a NULL set in 8.0; MySQL 5.6 warns of a negative timeout of
 * MASTER_POS_WAIT and answers NULL (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gtid-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/replication-functions-synchronization.html.
 *
 * @visibility MySqlMemory
 */
final class Replication
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('GTID_SUBSET', 2, 2, fn (Frame $f, array $a): ?int => ($sets = $this->sets($f, $a)) === null ? null : (int) $sets[0]->within($sets[1])),
            new Routine('GTID_SUBTRACT', 2, 2, fn (Frame $f, array $a): ?string => ($sets = $this->sets($f, $a)) === null ? null : $sets[0]->subtract($sets[1])->text()),
            new Routine('WAIT_FOR_EXECUTED_GTID_SET', 1, 2, $this->executed(...)),
            new Routine('SOURCE_POS_WAIT', 2, 4, $this->position(...)),
            new Routine('MASTER_POS_WAIT', 2, 4, $this->position(...)),
            new Routine('WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS', 1, 3, $this->applied(...)),
        ];
    }

    /**
     * Reads both arguments as GTID sets, or answers null when one is NULL.
     *
     * @param list<Evaluable> $arguments
     * @return array{GtidSet, GtidSet}|null
     *
     * @throws SqlError When a set is malformed
     */
    public function sets(Frame $frame, array $arguments): ?array
    {
        $tags = !in_array($frame->context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820], true);
        $texts = [];
        foreach ($arguments as $argument) {
            $text = Convert::toText($argument->evaluate($frame), $argument->domain());
            if ($text === null) {
                return null;
            }
            $texts[] = $text;
        }
        $sets = [];
        foreach ($texts as $text) {
            $sets[] = GtidSet::parse($text, $tags) ?? throw AdministrationError::MalformedGtidSet->error(substr($text, 0, 200));
        }

        return [$sets[0], $sets[1]];
    }

    /**
     * WAIT_FOR_EXECUTED_GTID_SET(set[, timeout]).
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError Always, as the server has GTID_MODE OFF
     */
    public function executed(Frame $frame, array $arguments): ?int
    {
        if ($arguments[0]->evaluate($frame) === null) {
            throw AdministrationError::MalformedGtidSet->error('NULL');
        }
        if (isset($arguments[1])) {
            $timeout = Convert::toDouble($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context);
            if ($timeout === null || $timeout < 0) {
                throw StatementError::WrongArguments->error('WAIT_FOR_EXECUTED_GTID_SET.');
            }
        }

        throw AdministrationError::GtidModeOff->error('use WAIT_FOR_EXECUTED_GTID_SET');
    }

    /**
     * SOURCE_POS_WAIT(file, position[, timeout[, channel]]) and MASTER_POS_WAIT.
     *
     * @param list<Evaluable> $arguments
     * @return null
     *
     * @throws SqlError When the timeout is negative, or no channel is named from MySQL 8.1
     */
    public function position(Frame $frame, array $arguments): mixed
    {
        $release = $frame->context->modes->release;
        $file = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($file === null || $file === '') {
            return null;
        }
        (new Strings())->number($frame, $arguments[1]);
        if (isset($arguments[2])) {
            $timeout = Convert::toDouble($arguments[2]->evaluate($frame), $arguments[2]->domain(), $frame->context);
            if ($timeout !== null && $timeout < 0) {
                $name = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744 ? 'MASTER_POS_WAIT.' : 'SOURCE_POS_WAIT.';
                if ($release === GrammarRelease::MySql5651) {
                    $frame->context->diagnostics->warning(StatementError::WrongArguments, StatementError::WrongArguments->message($name));

                    return null;
                }

                throw StatementError::WrongArguments->error($name);
            }
        }
        if (isset($arguments[3])) {
            $arguments[3]->evaluate($frame);

            return null;
        }
        if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044], true)) {
            throw AdministrationError::ReplicaMultipleChannels->error();
        }

        return null;
    }

    /**
     * WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS(set[, timeout[, channel]]), which MySQL 5.6 to 8.0 have.
     *
     * @param list<Evaluable> $arguments
     * @return null
     *
     * @throws SqlError When the timeout is negative from 5.7, or the set NULL in 8.0
     */
    public function applied(Frame $frame, array $arguments, Domain $result): mixed
    {
        $release = $frame->context->modes->release;
        $set = $arguments[0]->evaluate($frame);
        if ($release === GrammarRelease::MySql5651) {
            foreach (array_slice($arguments, 1) as $argument) {
                $argument->evaluate($frame);
            }

            return null;
        }
        if ($set === null && $release !== GrammarRelease::MySql8044) {
            return null;
        }
        $timeout = isset($arguments[1]) ? Convert::toDouble($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context) : 0.0;
        if ($set === null || ($timeout !== null && $timeout < 0)) {
            throw StatementError::WrongArguments->error('WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS.');
        }

        return null;
    }
}
