<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\UnknownSchemaBehavior;

final class UnknownSchemaBehaviorTest extends TestCase
{
    public function testCasesReturnsAllCases(): void
    {
        $cases = UnknownSchemaBehavior::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(UnknownSchemaBehavior::Passthrough, $cases);
        $this->assertContains(UnknownSchemaBehavior::EmptyResult, $cases);
        $this->assertContains(UnknownSchemaBehavior::Notice, $cases);
        $this->assertContains(UnknownSchemaBehavior::Exception, $cases);
    }

    #[DataProvider('providerCaseValues')]
    public function testCaseHasCorrectValue(UnknownSchemaBehavior $case, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $case->value);
    }

    /**
     * @return array<string, array{UnknownSchemaBehavior, string}>
     */
    public static function providerCaseValues(): array
    {
        return [
            'Passthrough' => [UnknownSchemaBehavior::Passthrough, 'passthrough'],
            'EmptyResult' => [UnknownSchemaBehavior::EmptyResult, 'empty'],
            'Notice' => [UnknownSchemaBehavior::Notice, 'notice'],
            'Exception' => [UnknownSchemaBehavior::Exception, 'exception'],
        ];
    }

    #[DataProvider('providerFromValue')]
    public function testFromReturnsCorrectCase(string $value, UnknownSchemaBehavior $expected): void
    {
        $this->assertSame($expected, UnknownSchemaBehavior::from($value));
    }

    /**
     * @return array<string, array{string, UnknownSchemaBehavior}>
     */
    public static function providerFromValue(): array
    {
        return [
            'passthrough' => ['passthrough', UnknownSchemaBehavior::Passthrough],
            'empty' => ['empty', UnknownSchemaBehavior::EmptyResult],
            'notice' => ['notice', UnknownSchemaBehavior::Notice],
            'exception' => ['exception', UnknownSchemaBehavior::Exception],
        ];
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $result = UnknownSchemaBehavior::tryFrom('invalid');

        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertNull($result);
    }
}
