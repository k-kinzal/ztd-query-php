<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Expression\ComparisonRule;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;

#[CoversClass(ComparisonRule::class)]
#[Medium]
final class ComparisonRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerContextualized(): iterable
    {
        yield '5.7' => ['mysql-5.7.44'];
        yield '8.0' => ['mysql-8.0.44'];
        yield '8.4' => ['mysql-8.4.7'];
        yield '9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerContextualized')]
    public function testLowerRejectsWidthBeforeALaterCallButAllowsSyntaxNotices(string $release): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze('SELECT (1,2)=(3,4,5), ABS()');
            self::fail('The first comparison has incompatible outer widths.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(OperandColumns::class, $failure->problem);
            self::assertSame([2, 3, 20, false], [$failure->problem->expected, $failure->problem->actual, $failure->end, $failure->aborts]);
        }
    }

    public function testRejectedLeavesUnrelatedTemporalValidationAtItsOwnBoundary(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = $platform->profile('mysql-8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $source = $platform->parser($profile)->parse("SELECT DATE 'bad'")->find('bool_pri')[0];
        self::assertNull((new ComparisonRule())->rejected($source, $lowering));
    }

    public function testWrittenWidthRecognizesGroupedRowsWithoutResolvingTheirElements(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = $platform->profile('mysql-8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $row = $platform->parser($profile)->parse('SELECT ((missing,ABS()))')->find('expr')[0];
        $scalar = $platform->parser($profile)->parse('SELECT ABS(1)')->find('expr')[0];
        self::assertSame(2, (new ComparisonRule())->writtenWidth($row, $lowering));
        self::assertNull((new ComparisonRule())->writtenWidth($scalar, $lowering));
    }

    public function testLowerRejectsLegacyWidthWithoutPublishingAnInvalidComparison(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT (1,2)=(3,4,5)');
            self::fail('A contradictory comparison has no scalar interpretation.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(OperandColumns::class, $failure->problem);
            self::assertSame([2, 3, false], [$failure->problem->expected, $failure->problem->actual, $failure->aborts]);
        }
    }

    public function testLowerKeepsAValidComparison(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (1,2) <=> (3,4)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $operation->query->items[0]);
        self::assertInstanceOf(Comparison::class, $operation->query->items[0]->expression);
        self::assertSame([], $operation->diagnostics());
    }
}
