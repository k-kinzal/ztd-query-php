<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Transaction\Xa\FormatIdentifier;

#[CoversClass(FormatIdentifier::class)]
#[Medium]
final class FormatIdentifierTest extends TestCase
{
    #[DataProvider('providerValidSpellings')]
    public function testPreservesTheAcceptedNumericOperand(string $spelling): void
    {
        self::assertSame($spelling, (new FormatIdentifier($spelling))->spelling);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerValidSpellings(): iterable
    {
        yield 'default' => ['1'];
        yield 'leading zeros' => ['0007'];
        yield 'maximum' => ['9223372036854775807'];
        yield 'hexadecimal' => ["X'7fffffffffffffff'"];
        yield 'hexadecimal prefix' => ['0x2a'];
        yield 'decimal syntax accepted by ulong_num' => ['1.5e2'];
        yield 'fraction syntax accepted by ulong_num' => ['.5'];
    }

    #[DataProvider('providerInvalidSpellings')]
    public function testRejectsOutOfDomainOperands(string $spelling): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new FormatIdentifier($spelling);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidSpellings(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'decimal overflow' => ['9223372036854775808'];
        yield 'hexadecimal overflow' => ['0x8000000000000000'];
        yield 'expression' => ['1 + 1'];
        yield 'string' => ["'1'"];
        yield 'empty' => [''];
        yield 'variable' => ['@format'];
    }
}
