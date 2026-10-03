<?php

declare(strict_types=1);

namespace Tests\Contract;

use InvalidArgumentException;

/**
 * Transaction requests whose observed behavior distinguishes omitted and explicit characteristics.
 */
final class TransactionCorpus
{
    /**
     * Each case uses fresh connections so a RELEASE request cannot affect the next comparison.
     * @throws InvalidArgumentException When the dialect has no transaction observation corpus
     * @return iterable<array{list<string>, string, list<string>}>
     */
    public static function cases(string $dialect): iterable
    {
        return match ($dialect) {
            'mysql' => self::mysql(),
            'pg' => self::postgres(),
            default => throw new InvalidArgumentException('Use mysql or pg.'),
        };
    }

    /**
     * Observes actual access, isolation, transaction state, snapshot warnings, and release behavior.
     * @return iterable<array{list<string>, string, list<string>}>
     */
    public static function mysql(): iterable
    {
        $status = 'SELECT t.ACCESS_MODE, t.ISOLATION_LEVEL, t.STATE FROM performance_schema.events_transactions_current t JOIN performance_schema.threads h ON t.THREAD_ID = h.THREAD_ID WHERE h.PROCESSLIST_ID = CONNECTION_ID()';
        foreach (['', ' READ ONLY', ' READ WRITE', ' WITH CONSISTENT SNAPSHOT', ' READ ONLY, WITH CONSISTENT SNAPSHOT', ' READ ONLY, READ WRITE', ' READ WRITE, READ ONLY', ' READ ONLY, READ ONLY', ' WITH CONSISTENT SNAPSHOT, WITH CONSISTENT SNAPSHOT'] as $options) {
            foreach ([0, 1] as $readOnly) {
                foreach (['REPEATABLE-READ', 'READ-COMMITTED'] as $isolation) {
                    yield [['SET SESSION transaction_read_only = ' . $readOnly, "SET SESSION transaction_isolation = '" . $isolation . "'"], 'START TRANSACTION' . $options, ['SHOW WARNINGS', $status]];
                }
            }
        }
        foreach (['COMMIT', 'ROLLBACK'] as $verb) {
            foreach (['', ' AND CHAIN', ' AND NO CHAIN'] as $chain) {
                foreach (['', ' RELEASE', ' NO RELEASE'] as $release) {
                    foreach ([0, 1, 2] as $default) {
                        yield [['SET SESSION completion_type = ' . $default, 'START TRANSACTION READ ONLY'], $verb . ' WORK' . $chain . $release, [$status]];
                    }
                }
            }
        }
    }

    /**
     * Observes isolation, access, and deferrability before and after chained completion.
     * @return iterable<array{list<string>, string, list<string>}>
     */
    public static function postgres(): iterable
    {
        $status = "SELECT current_setting('transaction_isolation'), current_setting('transaction_read_only'), current_setting('transaction_deferrable')";
        foreach (['BEGIN', 'START TRANSACTION'] as $verb) {
            foreach (['', ' ISOLATION LEVEL READ UNCOMMITTED', ' ISOLATION LEVEL READ COMMITTED', ' ISOLATION LEVEL REPEATABLE READ', ' ISOLATION LEVEL SERIALIZABLE'] as $isolation) {
                foreach (['', ' READ ONLY', ' READ WRITE'] as $access) {
                    foreach (['', ' DEFERRABLE', ' NOT DEFERRABLE'] as $deferrable) {
                        yield [[], $verb . $isolation . $access . $deferrable, [$status]];
                    }
                }
            }
        }
        foreach (['BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE, ISOLATION LEVEL READ COMMITTED, NOT DEFERRABLE, READ WRITE', 'BEGIN READ WRITE, READ ONLY, READ ONLY', 'BEGIN DEFERRABLE, NOT DEFERRABLE, DEFERRABLE'] as $request) {
            yield [[], $request, [$status]];
        }
        foreach (['COMMIT', 'END', 'ROLLBACK', 'ABORT'] as $verb) {
            foreach (['', ' AND CHAIN', ' AND NO CHAIN'] as $chain) {
                yield [['BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE'], $verb . ' WORK' . $chain, [$status]];
            }
        }
    }
}
