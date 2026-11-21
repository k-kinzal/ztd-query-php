<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;

#[CoversClass(Leaves::class)]
#[Small]
final class LeavesTest extends TestCase
{
    public function testRecordReturnsTheValueItself(): void
    {
        $leaves = new Leaves();
        $name = new Name('a');

        self::assertSame($name, $leaves->record($name));
    }

    public function testAllAnswersEveryRecordedLeafInOrder(): void
    {
        $leaves = new Leaves();
        $first = new Name('a');
        $second = new Name('b');

        $leaves->record($first);
        $leaves->record($second);

        self::assertSame([$first, $second], $leaves->all());
        self::assertSame([], (new Leaves())->all());
    }
}
