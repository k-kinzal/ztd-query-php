<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;

final class UnsupportedSqlBehaviorTest extends TestCase
{
    public function testCasesReturnsAllCases(): void
    {
        $cases = UnsupportedSqlBehavior::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(UnsupportedSqlBehavior::Ignore, $cases);
        $this->assertContains(UnsupportedSqlBehavior::Notice, $cases);
        $this->assertContains(UnsupportedSqlBehavior::Exception, $cases);
    }

    #[DataProvider('providerCaseValues')]
    public function testCaseHasCorrectValue(UnsupportedSqlBehavior $case, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    /**
     * @return array<string, array{UnsupportedSqlBehavior, string}>
     */
    public static function providerCaseValues(): array
    {
        return [
            'Ignore' => [UnsupportedSqlBehavior::Ignore, 'ignore'],
            'Notice' => [UnsupportedSqlBehavior::Notice, 'notice'],
            'Exception' => [UnsupportedSqlBehavior::Exception, 'exception'],
        ];
    }

    #[DataProvider('providerFromValue')]
    public function testFromReturnsCorrectCase(string $value, UnsupportedSqlBehavior $expected): void
    {
        $this->assertSame($expected, UnsupportedSqlBehavior::from($value));
    }

    /**
     * @return array<string, array{string, UnsupportedSqlBehavior}>
     */
    public static function providerFromValue(): array
    {
        return [
            'ignore' => ['ignore', UnsupportedSqlBehavior::Ignore],
            'notice' => ['notice', UnsupportedSqlBehavior::Notice],
            'exception' => ['exception', UnsupportedSqlBehavior::Exception],
        ];
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $result = UnsupportedSqlBehavior::tryFrom('invalid');

        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertNull($result);
    }
}
