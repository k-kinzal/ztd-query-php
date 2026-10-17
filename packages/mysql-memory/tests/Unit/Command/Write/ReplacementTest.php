<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\Replacement;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Replacement::class)]
#[Small]
final class ReplacementTest extends TestCase
{
    public function testReplaceUpdatesTheRowInPlaceOnTheLastUniqueKeyAndDeletesItOnAnother(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, u INT UNIQUE, v INT); INSERT INTO t VALUES (1, 1, 0), (2, 2, 0)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $replacement = new Replacement($table, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), $session);

        self::assertSame([[2, true], [1, true], [1, false]], [$replacement->replace([3, 2, 9], 2, $table->definition->keys[1]), $replacement->replace([3, 2, 9], 2, $table->definition->keys[1]), $replacement->replace([3, 1, 9], 1, $table->definition->keys[0])]);
        self::assertSame([2 => [3, 2, 9]], $table->data->rows);
    }

    public function testReplaceDeletesTheRowOnTheLastUniqueKeyOfATableWithADeleteTrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); CREATE TRIGGER g AFTER DELETE ON t FOR EACH ROW SET @deleted = OLD.id; INSERT INTO t VALUES (1, 0)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([1, false], (new Replacement($table, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), $session))->replace([1, 9], 1, $table->definition->keys[0]));
        self::assertSame([], $table->data->rows);
    }

    public function testLastUniqueAnswersTheLastUniqueKeyOfTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, u INT UNIQUE, v INT, KEY (v)); CREATE TABLE n (v INT, KEY (v))');
        $table = $session->instance->dictionary->table('d', 't');
        $plain = $session->instance->dictionary->table('d', 'n');
        self::assertNotNull($table);
        self::assertNotNull($plain);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(['u', null], [(new Replacement($table, $context, $session))->lastUnique()?->name, (new Replacement($plain, $context, $session))->lastUnique()?->name]);
    }
}
