<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\RoutineCalls;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RoutineCalls::class)]
#[Small]
final class RoutineCallsTest extends TestCase
{
    public function testResultTypesANativeFunction(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $int = new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull);
        $fact = (new RoutineCalls())->result(new FunctionCall(new Name('Abs'), [new CallArgument(new NumberLiteral('1'))]), [$int], $derivation);

        self::assertEquals($int->type, $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testResultDependsOnAStoredOrLoadableFunction(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.6.51', null, ParameterStyle::Native), null, [], false));
        $stored = (new RoutineCalls())->result(new FunctionCall(new Name('json_array')), [], $derivation);
        $qualified = (new RoutineCalls())->result(new FunctionCall(new Name('abs'), [], new Name('db')), [], $derivation);

        self::assertInstanceOf(Dependent::class, $stored->type);
        self::assertInstanceOf(UndeclaredRoutine::class, $stored->type->missing[0]);
        self::assertInstanceOf(Dependent::class, $qualified->type);
        self::assertEquals([new UndeclaredRoutine(new QualifiedName(new Name('abs'), new Name('db')))], $qualified->type->missing);
    }

    public function testResultReportsWhatTheServerRejects(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $int = new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull);
        $count = (new RoutineCalls())->result(new FunctionCall(new Name('abs')), [], $derivation);
        $named = (new RoutineCalls())->result(new FunctionCall(new Name('abs'), [new CallArgument(new NumberLiteral('1'), new Name('x'))]), [$int], $derivation);
        $reserved = (new RoutineCalls())->result(new FunctionCall(new Name('internal_use_terminology_previous')), [], $derivation);

        self::assertInstanceOf(Invalid::class, $count->type);
        self::assertInstanceOf(WrongArgumentCount::class, $count->type->cause);
        self::assertInstanceOf(Invalid::class, $named->type);
        self::assertInstanceOf(NamedArgument::class, $named->type->cause);
        self::assertInstanceOf(Invalid::class, $reserved->type);
        self::assertInstanceOf(ReservedFunction::class, $reserved->type->cause);
        self::assertCount(3, $derivation->facts()->diagnostics);
    }
}
