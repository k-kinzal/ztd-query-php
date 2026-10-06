<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InList::class)]
#[Medium]
final class InListTest extends TestCase
{
    public function testDeriveScalarIsNullWhenAnElementCanBe(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertSame(Nullability::Nullable, $derivation->scalar(new InList(new NumberLiteral('1'), [new NumberLiteral('2'), new NullLiteral()]), $derivation->environment())->nullability);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testDeriveScalarReportsAnElementOfAnotherWidth(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $derivation->scalar(new InList(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), [new NumberLiteral('3')]), $derivation->environment());

        self::assertSame('Operand should contain 2 column(s), not 1.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesTheNegatedList(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new InList(new NumberLiteral('1'), [new NumberLiteral('2'), new StringLiteral(['x'])], true))->render($out);

        self::assertSame("1 NOT IN (2, 'x')", (new Lexical())->join($out->pieces()));
    }

    public function testAComparisonOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of IN needs a grouping to keep its place.');

        new InList(new Comparison(ComparisonOperator::Equal, new NumberLiteral('1'), new NumberLiteral('2')), [new NumberLiteral('3')]);
    }

    public function testAnEmptyListIsRejected(): void
    {
        $this->expectExceptionMessage('An IN list has at least one element.');

        new InList(new NumberLiteral('1'), []);
    }
}
