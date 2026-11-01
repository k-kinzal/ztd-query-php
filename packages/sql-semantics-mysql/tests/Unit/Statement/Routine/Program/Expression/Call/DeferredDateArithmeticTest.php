<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic;

#[CoversClass(DeferredDateArithmetic::class)]
#[Medium]
final class DeferredDateArithmeticTest extends TestCase
{
    public function testDeriveScalarKeepsThePreparationConditionOutOfDiagnostics(): void
    {
        $value = new DeferredDateArithmetic(false, new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3'), \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderRetainsAllWrittenOperands(): void
    {
        $value = new DeferredDateArithmetic(false, new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3'), \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day);
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $out = new \SqlSemantics\Rendering\Output($platform->codec((new Semantics(Dialect::MySql))->profile()));
        $value->render($out);
        self::assertSame('DATE_ADD((1, 2), INTERVAL 3 DAY)', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testOperandsKeepsEveryPreparationDependency(): void
    {
        $value = new DeferredDateArithmetic(false, new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3'), \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day);
        self::assertCount(2, $value->operands());
    }

    public function testProblemRetainsTheFirstRowConstraint(): void
    {
        $value = new DeferredDateArithmetic(false, new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3'), \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day);
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame([1, 2], [$problem->expected, $problem->actual]);
    }
    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, string}>
     */
    public static function providerWrittenForms(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['ADDDATE((1, 2), INTERVAL 1 DAY)', 'SUBDATE((1, 2), INTERVAL 1 DAY)'] as $expression) {
                foreach (['', ' AS chosen'] as $alias) {
                    yield $release->value . '/program/' . $expression . $alias => [$release, 'CREATE PROCEDURE wrapper() SELECT ' . $expression . $alias];
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerWrittenForms')]
    public function testRenderPreservesTheStructureBeforeOutputSpelling(\SqlSemantics\Contract\GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql));
        $information = $statement->statementInformation();
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($release, $information->profile->lexical, $information->client));
        $statement->render($out);
        self::assertSame($formatter->format($sql), $formatter->format((new \SqlSemantics\Rendering\Lexical())->join($out->canonical())));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }
}
