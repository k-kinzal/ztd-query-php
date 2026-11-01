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
        $count = (new RoutineCalls())->result(new FunctionCall(new Name('regexp_like')), [], $derivation);
        $named = (new RoutineCalls())->result(new FunctionCall(new Name('regexp_like'), [new CallArgument(new NumberLiteral('1'), new Name('x')), new CallArgument(new NumberLiteral('2'))]), [$int, $int], $derivation);
        $reserved = (new RoutineCalls())->result(new FunctionCall(new Name('internal_use_terminology_previous')), [], $derivation);

        self::assertInstanceOf(Invalid::class, $count->type);
        self::assertInstanceOf(WrongArgumentCount::class, $count->type->cause);
        self::assertInstanceOf(Invalid::class, $named->type);
        self::assertInstanceOf(NamedArgument::class, $named->type->cause);
        self::assertInstanceOf(Invalid::class, $reserved->type);
        self::assertInstanceOf(ReservedFunction::class, $reserved->type->cause);
        self::assertCount(3, $derivation->facts()->diagnostics);
    }

    public function testResultRejectsAReservedFunctionWhateverItsArguments(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $int = new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull);
        $reserved = (new RoutineCalls())->result(new FunctionCall(new Name('convert_cpu_id_mask'), [new CallArgument(new NumberLiteral('1'), null), new CallArgument(new NumberLiteral('2'), null)]), [$int, $int], $derivation);

        self::assertInstanceOf(Invalid::class, $reserved->type);
        self::assertInstanceOf(ReservedFunction::class, $reserved->type->cause);
    }

    public function testResultTypesAStoredFunctionTheSessionHolds(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'), functions: ['d.f' => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer()]);
        $operation = $semantics->analyze('SELECT f(1), g(1)', $semantics->context(null, true, new \SqlSemantics\Contract\SearchPath('d'), $settings));
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);

        [$first, $second] = $select->items;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $first);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $second);

        self::assertSame([Known::class, Dependent::class], [$first->expression->scalarInformation()->type::class, $second->expression->scalarInformation()->type::class]);
    }

    public function testSignatureReportsNativeConstraintsWithoutDerivingOperands(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.7.44', null, ParameterStyle::Native), null, [], false));
        $call = new FunctionCall(new Name('ST_SRID'), [new CallArgument(new NumberLiteral('1')), new CallArgument(new NumberLiteral('4326'))]);
        $fact = (new RoutineCalls())->signature($call, $derivation);
        self::assertInstanceOf(Invalid::class, $fact?->type);
        self::assertInstanceOf(WrongArgumentCount::class, $fact->type->cause);
        self::assertSame([$fact->type->cause], $derivation->facts()->diagnostics);
    }
    public function testResultKeepsLegacyStoredFunctionWidthsThroughAnAssignment(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.6.51');
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_bin'), functions: ['d.f' => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, 11)]);
        $operation = $semantics->analyze('SELECT @a := f()', $semantics->context(null, true, new \SqlSemantics\Contract\SearchPath('d'), $settings));
        $type = $operation->field(0)->type;
        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $type->descriptor);
        self::assertSame(11, $type->descriptor->metadataLength());
    }

}
