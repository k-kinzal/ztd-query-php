<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewReadLayout;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ViewReadLayout::class)]
#[Medium]
final class ViewReadLayoutTest extends TestCase
{
    public function testNoInputRecognizesDualAndRejectsPhysicalInputs(): void
    {
        $inspection = new Inspection();
        $withDual = $inspection->inspect('SELECT 1 FROM DUAL')->statement;
        $withTable = $inspection->inspect('SELECT 1 FROM app.accounts')->statement;
        self::assertInstanceOf(Select::class, $withDual);
        self::assertInstanceOf(Select::class, $withTable);
        $layout = new ViewReadLayout();
        self::assertTrue($layout->noInput($withDual));
        self::assertFalse($layout->noInput($withTable));
    }

    public function testIntegerUsesReopenedDimensionsAcrossTheReleaseBoundary(): void
    {
        $layout = new ViewReadLayout();
        $padded = new Grouped(new Unary(UnaryOperator::Plus, new NumberLiteral('0000000000100000000')));
        $legacy = $layout->integer($padded, GrammarRelease::MySql5651);
        $modern = $layout->integer($padded, GrammarRelease::MySql8044);
        self::assertSame(IntegralKind::Int, $legacy?->kind);
        self::assertSame('9', $legacy->width);
        self::assertSame(IntegralKind::BigInt, $modern?->kind);
        self::assertSame('10', $modern->width);
        self::assertSame('2', $layout->integer(new SignedLiteral(true, new NumberLiteral('0')), GrammarRelease::MySql5651)?->width);
        self::assertSame(IntegralKind::BigInt, $layout->integer(new SignedLiteral(true, new NumberLiteral('9223372036854775808')), GrammarRelease::MySql910)?->kind);
        self::assertNull($layout->integer(new NumberLiteral('9223372036854775808'), GrammarRelease::MySql910));
        self::assertNull($layout->integer(new NumberLiteral('1.25'), GrammarRelease::MySql910));
    }
}
