<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Transaction;

use MySqlMemory\Instance;
use MySqlMemory\Session\Transaction\Creation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Creation::class)]
#[Small]
final class CreationTest extends TestCase
{
    public function testBeginKeepsTheNewTableUnpublished(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');

        self::assertTrue($session->transaction->active());
        self::assertTrue($session->transaction->creation->active);
        self::assertNotNull($session->transaction->creation->table);
        self::assertNull($session->instance->dictionary->table('d', 't'));
    }

    public function testCommitPublishesTheNewTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION; COMMIT');

        self::assertFalse($session->transaction->active());
        self::assertFalse($session->transaction->creation->active);
        self::assertNull($session->transaction->creation->table);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }

    public function testRollbackDiscardsTheNewTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION; ROLLBACK');

        self::assertFalse($session->transaction->active());
        self::assertFalse($session->transaction->creation->active);
        self::assertNull($session->transaction->creation->table);
        self::assertNull($session->instance->dictionary->table('d', 't'));
    }

    public function testRollbackOnDisconnectDiscardsTheNewTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');
        $session->transaction->disconnect();

        self::assertFalse($session->transaction->creation->active);
        self::assertNull($session->transaction->creation->table);
        self::assertNull($session->instance->dictionary->table('d', 't'));
    }

    public function testBeginForAnExistingTableKeepsItThroughRollback(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(1); CREATE TABLE IF NOT EXISTS d.t(b INT) START TRANSACTION');
        self::assertTrue($session->transaction->creation->active);
        self::assertNull($session->transaction->creation->table);
        $session->query('ROLLBACK');
        self::assertSame([1 => [1]], $session->instance->dictionary->table('d', 't')?->data->rows);
    }
}
