<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Database;

use MySqlMemory\Command\Definition\Database\FileName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileName::class)]
#[Small]
final class FileNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerNames(): iterable
    {
        yield 'ASCII' => ['Ab_123', 'Ab_123'];
        yield 'punctuation' => ['a.b-$', 'a@002eb@002d@0024'];
        yield 'Japanese' => ['あ表', '@3042@8868'];
        yield 'case pairs' => ['éÉαΑяЯ', '@0p@0P@6l@6L@r1@R1'];
        yield 'reserved device' => ['NUL', 'NUL@@@'];
        yield 'reserved port' => ['lpt9', 'lpt9@@@'];
        yield 'unreserved port' => ['com0', 'com0'];
        yield 'clock' => ['CLOCK$', 'CLOCK@0024'];
        yield 'old filename' => ['#mysql50#a-b', 'a-b'];
        yield 'old reserved name' => ['#mysql50#con', 'con'];
        yield 'old period' => ['#mysql50#a.b', ''];
        yield 'old slash' => ['#mysql50#a/b', ''];
        yield 'old backslash' => ['#mysql50#a\\b', ''];
        yield 'bare prefix' => ['#mysql50#', '@0023mysql50@0023'];
        yield 'uppercase prefix' => ['#MYSQL50#a.b', '@0023MYSQL50@0023a@002eb'];
    }

    #[DataProvider('providerNames')]
    public function testEncodedUsesTheObservedIdentifierSpelling(string $name, string $expected): void
    {
        self::assertSame($expected, (new FileName())->encoded($name));
    }

    public function testLettersPreservesTheObservedLetterCase(): void
    {
        $letters = (new FileName())->letters();
        self::assertSame(['@0p', '@0P', '@6l', '@6L'], [$letters['é'], $letters['É'], $letters['α'], $letters['Α']]);
        self::assertArrayNotHasKey('表', $letters);
    }
}
