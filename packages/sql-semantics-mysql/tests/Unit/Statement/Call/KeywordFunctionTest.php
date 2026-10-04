<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;

#[CoversClass(KeywordFunction::class)]
#[Small]
final class KeywordFunctionTest extends TestCase
{
    public function testArityAnswersTheArgumentCountsOfTheGrammar(): void
    {
        self::assertSame([0, 0], KeywordFunction::Database->arity());
        self::assertSame([2, -1], KeywordFunction::Interval->arity());
        self::assertSame([0, -1], KeywordFunction::GeometryCollection->arity());
    }

    public function testResultAnswersTheResultCode(): void
    {
        self::assertSame('+C', KeywordFunction::Coalesce->result());
        self::assertSame('ZR', KeywordFunction::If->result());
    }
}
