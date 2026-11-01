<?php

declare(strict_types=1);

namespace Tests\Unit\Concurrency;

use MySqlMemory\Concurrency\Transactions;
use MySqlMemory\Instance;
use MySqlMemory\Storage\Heap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Transactions::class)]
#[Small]
final class TransactionsTest extends TestCase
{
    public function testOfAnswersTheTransactionOfASession(): void
    {
        $instance = new Instance();
        $session = $instance->connect();

        self::assertSame([$session->transaction, null], [$instance->transactions->of($session->id), $instance->transactions->of(99)]);
    }

    public function testOpenTakesTheCommitsSoFarAsTheSnapshot(): void
    {
        $transactions = new Transactions();
        $transactions->sequence = 4;

        self::assertSame([4, [7 => 4]], [$transactions->open(7), $transactions->views]);
    }

    public function testCloseForgetsTheViewAndTheVersionsNoViewNeeds(): void
    {
        $transactions = new Transactions();
        $transactions->open(7);
        $transactions->archive([3 => [new Heap(), [1 => [5]]]]);
        $transactions->close(7);

        self::assertSame([[], []], [$transactions->views, $transactions->history]);
    }

    public function testCommitNumbersTheCommitOfATransaction(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect();
        $session->query('CREATE TABLE d.t (a INT); BEGIN; INSERT INTO d.t VALUES (1)');
        $instance->transactions->commit($session->transaction);

        self::assertSame(1, $instance->transactions->sequence);
    }

    public function testArchiveKeepsTheEarlierVersionsForAnOlderView(): void
    {
        $transactions = new Transactions();
        $heap = new Heap();
        $transactions->open(7);
        $number = $transactions->archive([3 => [$heap, [1 => [5]]]]);

        self::assertSame([1, [3 => [[1, $heap, [1 => [5]]]]]], [$number, $transactions->history]);
    }

    public function testApplyCommitsTheRowsOfAPreparedBranch(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $instance->transactions->apply([[$table, $table->data, 2, null], [$table, $table->data, 3, [3]], [$table, new Heap(), 1, null]]);

        self::assertSame([[1 => [1], 3 => [3]], 2], [$table->data->rows, $instance->transactions->sequence]);
    }

    public function testPruneKeepsTheCommitsTheOldestViewDoesNotSee(): void
    {
        $transactions = new Transactions();
        $heap = new Heap();
        $transactions->open(7);
        $transactions->archive([3 => [$heap, [1 => [5]]]]);
        $transactions->open(8);
        $transactions->archive([3 => [$heap, [2 => [6]]]]);
        $transactions->close(7);

        self::assertSame([[3 => [[2, $heap, [2 => [6]]]]]], [$transactions->history]);
    }

    public function testVisibleShowsTheRowsAsTheSnapshotLeftThemWithTheReadersOwnChanges(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect();
        $writer = $instance->connect();
        $reader->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2)');
        $reader->query('BEGIN; SELECT * FROM d.t; INSERT INTO d.t VALUES (3)');
        $writer->query('DELETE FROM d.t WHERE a = 1; BEGIN; UPDATE d.t SET a = 20 WHERE a = 2');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([1 => [1], 2 => [2], 3 => [3]], $instance->transactions->visible($table, $reader->transaction, (int) $reader->transaction->snapshot));
    }

    public function testLatestGoesThroughTheLatestRowsAndTheRowsOthersDeleted(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect();
        $writer = $instance->connect();
        $reader->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2)');
        $writer->query('BEGIN; DELETE FROM d.t WHERE a = 1; UPDATE d.t SET a = 20 WHERE a = 2');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([1 => [1], 2 => [20]], $instance->transactions->latest($table, $reader->transaction));
    }

    public function testEarlierAnswersTheCommittedVersionsOfTheRowsOthersChanged(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect();
        $writer = $instance->connect();
        $reader->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1)');
        $writer->query('BEGIN; UPDATE d.t SET a = 10; INSERT INTO d.t VALUES (2)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([1 => [1], 2 => null], $instance->transactions->earlier($table, $reader->transaction));
    }

    public function testCommittedAnswersTheCommittedVersionOfARowAnotherTransactionChanged(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $reader = $instance->connect();
        $writer = $instance->connect();
        $reader->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2)');
        $writer->query('BEGIN; UPDATE d.t SET a = 10 WHERE a = 1');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([[[1]], null, null], [$instance->transactions->committed($table, 1, $reader->transaction), $instance->transactions->committed($table, 2, $reader->transaction), $instance->transactions->committed($table, 1, $writer->transaction)]);
    }

    public function testVictimChoosesTheLightestTransactionAndTheRequesterBetweenEquals(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect();
        $second = $instance->connect();
        $first->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2), (3)');
        $first->query('BEGIN; SELECT * FROM d.t WHERE a = 1 FOR UPDATE');
        $second->query('BEGIN; SELECT * FROM d.t WHERE a = 2 FOR UPDATE');
        $instance->transactions->locks->waits[$second->id] = [$first->id];
        $equal = $instance->transactions->victim([$first->id, $second->id]);
        $first->query('UPDATE d.t SET a = 30 WHERE a = 3');

        self::assertSame([$first->id, $second->id], [$equal, $instance->transactions->victim([$first->id, $second->id])]);
    }

    public function testWeightCountsTheChangesAndTheLockStructures(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect();
        $session->query('CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2); BEGIN; UPDATE d.t SET a = a + 1');

        self::assertSame([4, 0], [$instance->transactions->weight($session->id), $instance->transactions->weight(99)]);
    }

    public function testRestoredPutsEarlierVersionsBackInRowNumberOrder(): void
    {
        self::assertSame([1 => [1], 2 => [2]], (new Transactions())->restored([2 => [2], 3 => [3]], [1 => [1], 3 => null]));
    }
}
