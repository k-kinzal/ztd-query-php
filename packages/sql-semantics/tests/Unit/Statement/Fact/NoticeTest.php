<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Fact\Notice::class)]
#[Medium]
final class NoticeTest extends TestCase
{
    public function testWarningOwnershipRetainsItsSubjectAndInstruction(): void
    {
        $warning = self::createStub(\SqlSemantics\Statement\Fact\Warning::class);
        $subject = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $instruction = new \SqlSemantics\Platform\Sqlite\Statement\Query\Select([new \SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn($subject)]);
        $notice = new \SqlSemantics\Statement\Fact\Notice($warning, $subject, $instruction);
        self::assertSame([$warning, $subject, $instruction], [$notice->warning, $notice->subject, $notice->instruction]);
        $unlocated = new \SqlSemantics\Statement\Fact\Notice($warning);
        self::assertNull($unlocated->subject);
        self::assertNull($unlocated->instruction);
    }
}
