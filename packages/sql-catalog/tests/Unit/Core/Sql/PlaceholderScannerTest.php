<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Sql\PlaceholderRef;
use SqlCatalog\Core\Sql\PlaceholderScanner;
use SqlCatalog\Core\Sql\SqlLexer;
use SqlCatalog\Core\Sql\SqlToken;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(PlaceholderScanner::class)]
#[UsesClass(PlaceholderRef::class)]
#[UsesClass(SqlLexer::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class PlaceholderScannerTest extends TestCase
{
    public function testScanFindsPositionalParametersInOrder(): void
    {
        $found = (new PlaceholderScanner())->scan(TextPattern::fromText('INSERT INTO t VALUES (?, ?)'));
        self::assertCount(2, $found);
        self::assertSame([0, 1], array_map(static fn (PlaceholderRef $ref): int => $ref->position, $found));
    }

    public function testScanFindsNamedParameters(): void
    {
        $found = (new PlaceholderScanner())->scan(TextPattern::fromText('UPDATE t SET a = :a WHERE id = :id'));
        self::assertSame(['a', 'id'], array_map(static fn (PlaceholderRef $ref): ?string => $ref->name, $found));
    }

    public function testScanIgnoresQuestionMarksInsideStrings(): void
    {
        $found = (new PlaceholderScanner())->scan(TextPattern::fromText("SELECT '?' FROM t WHERE a = ?"));
        self::assertCount(1, $found);
    }

    public function testScanIgnoresPostgresCasts(): void
    {
        self::assertCount(0, (new PlaceholderScanner())->scan(TextPattern::fromText('SELECT a::text FROM t')));
    }

    public function testScanReadsThroughAGapWithoutBreakingTheStringAroundIt(): void
    {
        $pattern = TextPattern::fromText("SELECT * FROM t WHERE a = '")
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())))
            ->concat(TextPattern::fromText("' AND b = ?"));
        self::assertCount(1, (new PlaceholderScanner())->scan($pattern));
    }

    public function testNameOfReadsEveryParameterSyntax(): void
    {
        $scanner = new PlaceholderScanner();
        self::assertNull($scanner->nameOf('?'));
        self::assertSame('id', $scanner->nameOf(':id'));
        self::assertSame('1', $scanner->nameOf('$1'));
        self::assertSame('2', $scanner->nameOf('?2'));
    }
}
