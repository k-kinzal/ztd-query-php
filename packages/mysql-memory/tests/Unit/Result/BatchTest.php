<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Batch::class)]
#[Small]
final class BatchTest extends TestCase
{
    public function testWarningsAnswersThoseOfTheLastReply(): void
    {
        self::assertSame([2, 0], [(new Batch([new Completion(0, 0, 1), new Completion(0, 0, 2)]))->warnings(), (new Batch([]))->warnings()]);
    }
}
