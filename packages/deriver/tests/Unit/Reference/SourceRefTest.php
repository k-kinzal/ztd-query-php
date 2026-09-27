<?php

declare(strict_types=1);

namespace Tests\Unit\Reference;

use Deriver\Exception\InvalidInputException;
use Deriver\Reference\SourceRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceRef::class)]
#[Small]
final class SourceRefTest extends TestCase
{
    public function testIdPreservesTheSemanticContract(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('half-open');
        new SourceRef('s', 'a.php', 4, 3);
    }

    #[DataProvider('providerInvalidCoordinates')]
    public function testRejectsCoordinatesOutsideHalfOpenSourceRanges(int $start, int $end, int $line, int $column): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Source ranges must be half-open and coordinates positive.');
        new SourceRef('snapshot', 'file.php', $start, $end, $line, $column);
    }

    /**
     * @return iterable<string,array{int,int,int,int}>
     */
    public static function providerInvalidCoordinates(): iterable
    {
        yield 'negative start' => [-1,0,1,1];
        yield 'end before start' => [2,1,1,1];
        yield 'zero line' => [0,0,0,1];
        yield 'negative line' => [0,0,-1,1];
        yield 'zero column' => [0,0,1,0];
        yield 'negative column' => [0,0,1,-1];
    }

    public function testIdIncludesSnapshotPathAndBothByteEndpoints(): void
    {
        $source = new SourceRef('snapshot', 'src/file.php', 7, 29, 3, 5);
        self::assertSame('snapshot:src/file.php:7:29', $source->id());
        self::assertSame('snapshot', $source->snapshotId);
        self::assertSame('src/file.php', $source->path);
        self::assertSame(7, $source->start);
        self::assertSame(29, $source->end);
        self::assertSame(3, $source->line);
        self::assertSame(5, $source->column);
        self::assertSame('empty:file.php:0:0', (new SourceRef('empty', 'file.php', 0, 0))->id());
    }
}
