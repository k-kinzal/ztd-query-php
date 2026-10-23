<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(GroupedRow::class)]
#[Medium]
final class GroupedRowTest extends TestCase
{
    public function testRenderRetainsAnOdbcEscapeKindAroundTheOperand(): void
    {
        $operand = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new GroupedRow($operand, new \SqlSemantics\Statement\Identifier\Name('foo'));
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $out = new \SqlSemantics\Rendering\Output((new \SqlSemantics\Platform\MySql\Platform())->codec($semantics->profile()));
        $value->render($out);
        self::assertSame($operand, $value->operand);
        self::assertStringStartsWith('{ foo ', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeriveScalarKeepsTheRowWidth(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $context = $platform->context($platform->profile('mysql-8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $fact = $derivation->scalar(new GroupedRow(new Row([new NumberLiteral('1'), new NumberLiteral('2')])), $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $fact->type);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Tuple(2), $fact->type->descriptor);
    }

    public function testRenderRetainsGroupingAroundRows(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT ((1,2))=(1,2)');
        self::assertSame('SELECT ((1,2))=(1,2)', $operation->toString());
    }
}
