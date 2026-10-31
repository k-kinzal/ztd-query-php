<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\ComparisonWidth;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(ComparisonWidth::class)]
#[Medium]
final class ComparisonWidthTest extends TestCase
{
    /**
     * @return iterable<string, array{string, NoticePhase|null}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.6' => ['mysql-5.6.51', null];
        yield '5.7' => ['mysql-5.7.44', NoticePhase::Contextualization];
        yield '8.0' => ['mysql-8.0.44', NoticePhase::Contextualization];
        yield '8.4' => ['mysql-8.4.7', NoticePhase::Contextualization];
        yield '9.1' => ['mysql-9.1.0', NoticePhase::Contextualization];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerReleases')]
    public function testNoticeDistinguishesGrammarContextualizationAndPreparation(string $release, ?NoticePhase $phase): void
    {
        $left = new GroupedRow(new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $right = new Row([new NumberLiteral('3'), new NumberLiteral('4'), new NumberLiteral('5')]);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql, $release))->context());
        (new ComparisonWidth())->notice($left, $right, $derivation);
        $notices = array_values(array_filter($derivation->facts()->warnings, static fn ($warning): bool => $warning instanceof ParseFailure));
        self::assertSame($phase, $notices[0]->phase ?? null);
    }

    public function testNoticeDoesNotMoveScalarRowChecksIntoContextualization(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        (new ComparisonWidth())->notice(new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]), $derivation);
        self::assertSame([], $derivation->facts()->warnings);
    }

    public function testProblemRetainsBothOuterWidthsOnlyInTheEarlyCheckReleases(): void
    {
        $left = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $right = new Row([new NumberLiteral('3'), new NumberLiteral('4'), new NumberLiteral('5')]);
        $rule = new ComparisonWidth();
        $problem = $rule->problem($left, $right, \SqlSemantics\Contract\GrammarRelease::MySql847);
        self::assertSame([2, 3], [$problem?->expected, $problem?->actual]);
        self::assertNull($rule->problem($left, $right, \SqlSemantics\Contract\GrammarRelease::MySql5651));
    }

    public function testRowRecognizesWrittenRowsThroughParentheses(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        self::assertSame($row, (new ComparisonWidth())->row(new GroupedRow($row)));
        self::assertNull((new ComparisonWidth())->row(new NumberLiteral('1')));
    }
}
