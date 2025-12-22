<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnsupportedSqlBehavior::class)]
final class UnsupportedSqlBehaviorTest extends TestCase
{
    public function testCasesReturnsAllCases(): void
    {
        $cases = UnsupportedSqlBehavior::cases();

        self::assertCount(3, $cases);
        self::assertContains(UnsupportedSqlBehavior::Ignore, $cases);
        self::assertContains(UnsupportedSqlBehavior::Notice, $cases);
        self::assertContains(UnsupportedSqlBehavior::Exception, $cases);
    }

    public function testCaseHasCorrectValue(): void
    {
        self::assertSame('ignore', UnsupportedSqlBehavior::Ignore->value);
        self::assertSame('notice', UnsupportedSqlBehavior::Notice->value);
        self::assertSame('exception', UnsupportedSqlBehavior::Exception->value);
    }

    public function testFromReturnsCorrectCase(): void
    {
        self::assertSame(UnsupportedSqlBehavior::Ignore, UnsupportedSqlBehavior::from('ignore'));
        self::assertSame(UnsupportedSqlBehavior::Notice, UnsupportedSqlBehavior::from('notice'));
        self::assertSame(UnsupportedSqlBehavior::Exception, UnsupportedSqlBehavior::from('exception'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $result = UnsupportedSqlBehavior::tryFrom('invalid');

        self::assertSame(null, $result);
    }
}
