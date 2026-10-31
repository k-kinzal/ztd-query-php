<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\DiagnosticText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiagnosticText::class)]
#[Small]
final class DiagnosticTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function providerNames(): iterable
    {
        yield 'short' => ['db.t', false, 'db.t'];
        yield 'modern ascii' => [str_repeat('a', 150), false, str_repeat('a', 129)];
        yield 'modern two-byte' => [str_repeat('é', 100), false, str_repeat('é', 64) . '?'];
        yield 'modern three-byte' => ['a' . str_repeat('猫', 60), false, 'a' . str_repeat('猫', 42) . '??'];
        yield 'legacy ascii' => [str_repeat('a', 150), true, str_repeat('a', 100)];
        yield 'legacy two-byte' => ['a' . str_repeat('é', 100), true, 'a' . str_repeat('é', 49)];
        yield 'legacy three-byte' => [str_repeat('猫', 60), true, str_repeat('猫', 33)];
    }

    #[DataProvider('providerNames')]
    public function testMissingTablesBoundsTheDisplayedNameList(string $names, bool $legacy, string $expected): void
    {
        self::assertSame($expected, (new DiagnosticText())->missingTables($names, $legacy));
    }
    public function testBytesReplacesEachIncompleteByteAtTheBoundary(): void
    {
        self::assertSame("\n" . str_repeat('猫', 42) . '?', (new DiagnosticText())->bytes("\n" . str_repeat('猫', 60), 128));
        self::assertSame('猫??', (new DiagnosticText())->bytes('猫猫', 5));
        self::assertSame('猫', (new DiagnosticText())->bytes('猫猫', 5, true));
    }

}
