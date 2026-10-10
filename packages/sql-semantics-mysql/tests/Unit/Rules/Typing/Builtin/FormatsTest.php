<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Formats;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Formats::class)]
#[Small]
final class FormatsTest extends TestCase
{
    public function testWrittenIsAStringInTheConnectionCollationAsCoercibleAsTheFormat(): void
    {
        $call = new Invocation([new Domain(Kind::Date, Field::Date, 10), Domain::string(20, Collation::known('latin1_swedish_ci'))], [new NullLiteral(), new NullLiteral()], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals(Domain::string(0, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Implicit), (new Formats())->written($call, 1));
    }

    public function testLengthCountsEachSpecifierOfALiteralFormat(): void
    {
        $call = new Invocation([new Domain(Kind::Date, Field::Date, 10), Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')), Domain::integer(Field::LongLong, 11)], [new NullLiteral(), new StringLiteral(['%Y-%m %W%']), new NumberLiteral('5')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([73, 110], [(new Formats())->length($call, 1), (new Formats())->length($call, 2)]);
    }

    public function testParsedTypesTheResultByTheSpecifiers(): void
    {
        $string = Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'));
        $call = new Invocation([$string, $string, $string, $string, $string], [new NullLiteral(), new StringLiteral(['%Y %H']), new StringLiteral(['%H %d %f']), new StringLiteral(['%d']), new NullLiteral()], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals([new Domain(Kind::DateTime, Field::DateTime, 19), new Domain(Kind::Time, Field::Time, 17, 6), new Domain(Kind::Date, Field::Date, 10), new Domain(Kind::DateTime, Field::DateTime, 26, 6)], [(new Formats())->parsed($call, 1), (new Formats())->parsed($call, 2), (new Formats())->parsed($call, 3), (new Formats())->parsed($call, 4)]);
    }

    public function testLiteralReadsStringsAndOptionallyNumbers(): void
    {
        $call = new Invocation([Domain::integer(), Domain::null()], [new NumberLiteral('5'), new NullLiteral()], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame(['5', null, '', null], [(new Formats())->literal($call, 0, true), (new Formats())->literal($call, 0, false), (new Formats())->literal($call, 1, false), (new Formats())->literal($call, 1, true)]);
    }
}
