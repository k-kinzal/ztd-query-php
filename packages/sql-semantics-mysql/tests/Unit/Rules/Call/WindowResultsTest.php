<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Rules\Call\WindowResults;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(WindowResults::class)]
#[Small]
final class WindowResultsTest extends TestCase
{
    public function testResultTypesEachKindOfWindowFunction(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $int = new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull);
        $text = new ScalarFact(new Known(TypeClass::Character->descriptor()), Nullability::NotNull);
        $results = new WindowResults();
        $rank = $results->result(new WindowFunction(WindowFunctionKind::Rank, [], new Name('w')), [], $derivation);
        $lead = $results->result(new WindowFunction(WindowFunctionKind::Lead, [new NumberLiteral('1'), new NumberLiteral('1'), new StringLiteral(['a'])], new Name('w')), [$int, $int, $text], $derivation);
        $first = $results->result(new WindowFunction(WindowFunctionKind::FirstValue, [new NumberLiteral('1')], new Name('w')), [$int], $derivation);

        self::assertEquals(new Known(TypeClass::Unsigned->descriptor()), $rank->type);
        self::assertSame(Nullability::NotNull, $rank->nullability);
        self::assertEquals(new Known(TypeClass::Character->descriptor()), $lead->type);
        self::assertSame(Nullability::Nullable, $lead->nullability);
        self::assertSame($int->type, $first->type);
    }

    public function testResultReportsIgnoreNullsAndFromLast(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $int = new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull);
        $call = new WindowFunction(WindowFunctionKind::NthValue, [new NumberLiteral('1'), new NumberLiteral('2')], new Name('w'), NullTreatment::Ignore, CountingEdge::Last);
        (new WindowResults())->result($call, [$int, $int], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertInstanceOf(UnsupportedWindowing::class, $diagnostics[0]);
        self::assertInstanceOf(UnsupportedWindowing::class, $diagnostics[1]);
        self::assertSame([WindowingLimit::IgnoreNulls, WindowingLimit::FromLast], [$diagnostics[0]->limit, $diagnostics[1]->limit]);
    }
}
