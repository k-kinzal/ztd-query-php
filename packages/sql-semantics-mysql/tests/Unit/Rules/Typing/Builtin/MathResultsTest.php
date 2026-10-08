<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\MathResults;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(MathResults::class)]
#[Small]
final class MathResultsTest extends TestCase
{
    public function testRulesTypePiAndTheRealFunctions(): void
    {
        $rules = (new MathResults())->rules();

        self::assertEquals(Domain::double(8, 6), $rules['PI'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::double(23), $rules['SQRT'](new Invocation([Domain::integer()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }

    public function testSameKeepsTheClassOfTheArgument(): void
    {
        self::assertEquals(Domain::integer(Field::Long, 11), (new MathResults())->same(Domain::integer(Field::Long, 11)));
        self::assertEquals(Domain::double(23), (new MathResults())->same(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)));
    }

    public function testIntegralIsABigIntUpToEighteenDigits(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 21), (new MathResults())->integral(Domain::decimal(2, 1)));
        self::assertEquals(Domain::decimal(19, 0), (new MathResults())->integral(Domain::decimal(19, 1)));
    }

    public function testRoundedTakesTheDecimalsOfALiteral(): void
    {
        $number = new NumberLiteral('1.234');

        self::assertEquals(Domain::decimal(4, 2), (new MathResults())->rounded(new Invocation([Domain::decimal(4, 3), Domain::integer()], [$number, new NumberLiteral('2')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::decimal(4, 3), (new MathResults())->rounded(new Invocation([Domain::decimal(4, 3), Domain::integer()], [$number, new StringLiteral(['x'])], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::double(23), (new MathResults())->rounded(new Invocation([Domain::double()], [new NumberLiteral('1e0')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }

    public function testRulesTypeLogAsADouble(): void
    {
        $rules = (new MathResults())->rules();

        self::assertEquals(Domain::double(23), $rules['LOG'](new Invocation([Domain::integer(), Domain::integer()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }

    public function testLegacyMakesAbsOfAnIntegerABigintAndFloorAsLongAsItsArgumentIn57(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT ABS(CAST(1 AS UNSIGNED)), FLOOR(1.5)');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $items = array_map(static fn ($item) => $item instanceof SelectExpression ? $operation->facts->scalar($item->expression)->type : null, $statement->items);

        self::assertEquals([new Known(Domain::integer(Field::LongLong, 1, true)), new Known(Domain::integer(Field::LongLong, 4))], $items);
    }
}
