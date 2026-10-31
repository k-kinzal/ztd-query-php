<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access\Load;

use MySqlMemory\Command\Access\Load\SeparatedRecords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeparatedRecords::class)]
#[Small]
final class SeparatedRecordsTest extends TestCase
{
    public function testStartDiscardsLinesWithoutTheRequestedPrefix(): void
    {
        self::assertSame(5, (new SeparatedRecords())->start("no\np:x\n", 0, 'p:', "\n"));
    }

    public function testFieldPreservesAnEnclosedDelimiter(): void
    {
        self::assertSame(['a||b', 8, false], (new SeparatedRecords())->field('"a||b"||x', 0, '||', "\n", '"', '\\'));
    }

    public function testEndingAdvancesAcrossTheWholeFieldDelimiter(): void
    {
        self::assertSame([3, false], (new SeparatedRecords())->ending('a||b', 1, '||', "\n"));
    }

    public function testValueDistinguishesAnEscapedMarkerFromALiteralBackslash(): void
    {
        self::assertNull((new SeparatedRecords())->value('\\N', 'N', true, '"', '\\'));
        self::assertSame('\\N', (new SeparatedRecords())->value('\\\\N', '\\N', false, '"', '\\'));
    }

    public function testEscapedDecodesControlBytesAndPreservesOtherBytes(): void
    {
        self::assertSame(["\0", "\x08", "\n", "\r", "\t", "\x1a", ',', '\\'], array_map((new SeparatedRecords())->escaped(...), ['0', 'b', 'n', 'r', 't', 'Z', ',', '\\']));
    }

    /**
     * @return iterable<string, array{string, array<string, string>, int, list<list<string|null>>}>
     */
    public static function providerFiles(): iterable
    {
        yield 'empty' => ['', [], 0, []];
        yield 'default' => ["1\talpha\n2\t\\N\n", [], 0, [['1', 'alpha'], ['2', null]]];
        yield 'terminal field' => ["a\t", [], 0, [['a', '']]];
        yield 'empty record' => ["\n", [], 0, [['']]];
        yield 'enclosed multiline' => ["1,\"a,b\"\n2,\"a\nb\"\n", ['field:TERMINATED' => ',', 'field:ENCLOSED' => '"'], 0, [['1', 'a,b'], ['2', "a\nb"]]];
        yield 'null markers' => ["NULL,\"NULL\",\\N,\"\\N\",\\\\N\n", ['field:TERMINATED' => ',', 'field:ENCLOSED' => '"'], 0, [[null, 'NULL', null, null, '\\N']]];
        yield 'prefix after ignored header' => ["header\nxx1,a\nignore\nxx2,b\n", ['field:TERMINATED' => ',', 'line:STARTING' => 'xx'], 1, [['1', 'a'], ['2', 'b']]];
        yield 'multi-byte delimiters' => ['a||b<end>c||d', ['field:TERMINATED' => '||', 'line:TERMINATED' => '<end>'], 0, [['a', 'b'], ['c', 'd']]];
        yield 'doubled enclosure' => ['"a""b",c', ['field:TERMINATED' => ',', 'field:ENCLOSED' => '"'], 0, [['a"b', 'c']]];
        yield 'escaped separator' => ['a\\,b,c', ['field:TERMINATED' => ','], 0, [['a,b', 'c']]];
    }

    /**
     * @param array<string, string> $separators Effective options from the semantic structure
     * @param list<list<string|null>> $expected Decoded runtime data
     */
    #[DataProvider('providerFiles')]
    public function testReadRetainsRecordBoundariesAndNullIdentity(string $input, array $separators, int $ignored, array $expected): void
    {
        self::assertSame($expected, (new SeparatedRecords())->read($input, $separators, $ignored));
    }
}
