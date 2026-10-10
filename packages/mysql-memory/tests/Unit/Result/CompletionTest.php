<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Completion::class)]
#[Small]
final class CompletionTest extends TestCase
{
    public function testWarningsAnswersTheWarningCount(): void
    {
        $completion = new Completion(2, 5, 1, 'Records: 2  Duplicates: 0  Warnings: 1');

        self::assertSame(1, $completion->warnings());
        self::assertSame(2, $completion->affectedRows);
        self::assertSame(5, $completion->lastInsertId);
        self::assertSame('Records: 2  Duplicates: 0  Warnings: 1', $completion->info);
    }

    public function testWarningsIsZeroByDefault(): void
    {
        $completion = new Completion();

        self::assertSame(0, $completion->warnings());
        self::assertSame(0, $completion->affectedRows);
        self::assertSame(0, $completion->lastInsertId);
        self::assertSame('', $completion->info);
    }

    public function testWarningsOfAMultipleRowInsert(): void
    {
        $session = (new Instance())->connect();
        $replies = $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT); INSERT INTO d.t VALUES (1), (2)');

        self::assertEquals(new Completion(1), $replies[0]);
        self::assertEquals(new Completion(), $replies[1]);
        self::assertEquals(new Completion(2, 0, 0, 'Records: 2  Duplicates: 0  Warnings: 0'), $replies[2]);
    }
}
