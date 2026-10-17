<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Script;

use Generator;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Parse\Reader;
use MySqlMemory\Session\Problem\Script;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\State\StatementCounters;

/**
 * Executes a client's statements in order, yielding each reply before running the next statement.
 *
 * The wire server sends replies immediately, so subsequent statements observe bytes already sent
 * and each reply carries the transaction state at its own completion. In-process callers collect
 * the same sequence without producing network traffic. An error ends the sequence.
 * Source: https://dev.mysql.com/doc/c-api/8.4/en/c-api-multiple-queries.html.
 *
 * @visibility MySqlMemory
 */
final class Replies
{
    /**
     * @param Session $session The client executing the script
     */
    public function __construct(public readonly Session $session)
    {
    }

    /**
     * Yields a reply and whether the protocol announces more results after it.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound parameter values
     * @return Generator<int, array{Reply|SqlError, bool}, void, void>
     */
    public function run(string $sql, array $parameters = [], bool $prepared = false): Generator
    {
        $session = $this->session;
        $gone = $session->gone();
        if ($gone !== null) {
            yield [$gone, false];
            return;
        }
        try {
            $statements = $session->split($sql);
        } catch (SqlError $error) {
            $statements = (new Script())->statements($session->semantics(), $sql);
            if ($statements === []) {
                $session->activity->begin();
                StatementCounters::received($session);
                yield [(new Reader())->refused($error, $sql, $prepared, $session), false];
                return;
            }
        }
        foreach ($statements as $index => $statement) {
            $session->following = implode('', array_slice($statements, $index + 1));
            try {
                $reply = $session->execute($statement, $parameters, $prepared);
                $answers = $reply instanceof Batch ? $reply->replies : [$reply];
                foreach ($answers as $position => $answer) {
                    yield [$answer, $position < count($answers) - 1 || $index < count($statements) - 1];
                }
                if ($session->released) {
                    return;
                }
            } catch (SqlError $error) {
                foreach ((new Execution($session))->failed($error) as $answer) {
                    yield [$answer, !$answer instanceof SqlError];
                }
                break;
            }
        }
        $session->following = '';
    }
}
