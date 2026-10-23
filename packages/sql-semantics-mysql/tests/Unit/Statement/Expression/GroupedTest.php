<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Grouped::class)]
#[Medium]
final class GroupedTest extends TestCase
{
    public function testDeriveScalarHasTheFactsOfTheOperand(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $operand = new NullLiteral();
        $fact = $derivation->scalar(new Grouped($operand), $derivation->environment());

        self::assertInstanceOf(NullOnly::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertSame($operand, $fact->replacement);
    }

    public function testDeriveScalarRetainsTheBoundColumnThroughNestedParentheses(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(id INT PRIMARY KEY)');
        $query = $semantics->analyze('SELECT ((t.id)) AS x FROM t', [$table]);
        $field = $query->field(0);

        self::assertInstanceOf(Grouped::class, $field->expression);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $field->resolution);
        self::assertSame('id', $field->column()?->name->value);
        self::assertSame($field->resolution, $query->facts->scalar($field->expression->operand)->resolution);
    }

    public function testDeriveScalarKeepsTheReplacementOfAReducedOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operand = $semantics->analyze('SELECT (SELECT 1 LIMIT 0)')->field(0)->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $operand);
        $derivation = new Derivation($semantics->context());
        $fact = $derivation->scalar(new Grouped($operand), $derivation->environment());

        self::assertInstanceOf(NumberLiteral::class, $fact->replacement);
        self::assertSame('1', $fact->replacement->text);
    }

    public function testRenderLetsAWeakerOperandStandOnTheRight(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Logical(LogicalOperator::And, new NumberLiteral('1'), new Grouped(new Logical(LogicalOperator::Or, new NumberLiteral('2'), new NumberLiteral('3')))))->render($out);

        self::assertSame('1 AND (2 OR 3)', (new Lexical())->join($out->pieces()));
    }
}
