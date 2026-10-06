<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SubqueryRows::class)]
#[Medium]
final class SubqueryRowsTest extends TestCase
{
    public function testValueIsTheOneColumnOrARow(): void
    {
        $integer = new Known(new Integral(IntegralKind::Int));
        $one = new QueryFact([new Field(0, new OutputSlot(new Name('a'), $integer, Nullability::NotNull))], Comparison::AsciiInsensitive);
        $two = new QueryFact([new Field(0, new OutputSlot(new Name('a'), $integer, Nullability::NotNull)), new Field(1, new OutputSlot(new Name('b'), $integer, Nullability::NotNull))], Comparison::AsciiInsensitive);
        $open = new QueryFact([new OpenStar([new SessionState('x')])], Comparison::AsciiInsensitive);
        $rows = new SubqueryRows();

        self::assertEquals(new ScalarFact($integer, Nullability::Nullable), $rows->value($one));
        self::assertEquals(new Known(new Tuple(2)), $rows->value($two)->type);
        self::assertInstanceOf(Dependent::class, $rows->value($open)->type);
    }

    public function testTestCombinesTheColumnsAndReportsAWidthMismatch(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $integer = new Known(new Integral(IntegralKind::Int));
        $two = new QueryFact([new Field(0, new OutputSlot(new Name('a'), $integer, Nullability::NotNull)), new Field(1, new OutputSlot(new Name('b'), $integer, Nullability::Nullable))], Comparison::AsciiInsensitive);
        $nullability = (new SubqueryRows())->test(new ScalarFact($integer, Nullability::NotNull), $two, $derivation);

        self::assertSame(Nullability::Nullable, $nullability);
        self::assertSame('Operand should contain 1 column(s), not 2.', $derivation->facts()->diagnostics[0]->message());
    }
}
