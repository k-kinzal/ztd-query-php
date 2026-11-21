<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OpenStar;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

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
