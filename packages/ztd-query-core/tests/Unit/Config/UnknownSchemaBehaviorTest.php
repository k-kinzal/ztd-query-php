<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnknownSchemaBehavior::class)]
final class UnknownSchemaBehaviorTest extends TestCase
{
    public function testCasesReturnsAllCases(): void
    {
        $cases = UnknownSchemaBehavior::cases();

        self::assertCount(4, $cases);
        self::assertContains(UnknownSchemaBehavior::Passthrough, $cases);
        self::assertContains(UnknownSchemaBehavior::EmptyResult, $cases);
        self::assertContains(UnknownSchemaBehavior::Notice, $cases);
        self::assertContains(UnknownSchemaBehavior::Exception, $cases);
    }

    public function testCaseHasCorrectValue(): void
    {
        self::assertSame('passthrough', UnknownSchemaBehavior::Passthrough->value);
        self::assertSame('empty', UnknownSchemaBehavior::EmptyResult->value);
        self::assertSame('notice', UnknownSchemaBehavior::Notice->value);
        self::assertSame('exception', UnknownSchemaBehavior::Exception->value);
    }

    public function testFromReturnsCorrectCase(): void
    {
        self::assertSame(UnknownSchemaBehavior::Passthrough, UnknownSchemaBehavior::from('passthrough'));
        self::assertSame(UnknownSchemaBehavior::EmptyResult, UnknownSchemaBehavior::from('empty'));
        self::assertSame(UnknownSchemaBehavior::Notice, UnknownSchemaBehavior::from('notice'));
        self::assertSame(UnknownSchemaBehavior::Exception, UnknownSchemaBehavior::from('exception'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $result = UnknownSchemaBehavior::tryFrom('invalid');

        self::assertSame(null, $result);
    }
}
