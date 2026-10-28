<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterInference;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(ParameterInference::class)]
#[Small]
final class ParameterInferenceTest extends TestCase
{
    public function testInferKeepsMarkerOrderAndPreservesNullSiblingInference(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ?, ? + NULL, NULL DIV ?, ? + 1, CAST(? AS SIGNED), ~?');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $types = ParameterInference::infer($operation);

        self::assertSame([0, 1, 2, 3, 4, 5], array_keys($types));
        self::assertSame([[Kind::String, false], [Kind::String, false], [Kind::String, false], [Kind::Integer, false], [Kind::String, true], [Kind::String, false]], array_map(static fn (array $entry): array => [$entry[0]->kind, $entry[1]], $types));
        self::assertSame(65535, $types[5][0]->length);
        self::assertTrue($types[5][0]->collation->bytes());
    }

    public function testOperandWidensNumericInputsAndRetainsNullAsAStringContext(): void
    {
        $decimal = Domain::decimal(5, 2);

        self::assertSame(21, ParameterInference::operand(Domain::integer(length: 2), true)->length);
        self::assertSame(65, ParameterInference::operand($decimal, false)->precision());
        self::assertSame(30, ParameterInference::operand($decimal, false)->decimals);
        self::assertSame(Kind::String, ParameterInference::operand(Domain::null(), true)->kind);
        self::assertSame(Kind::Null, ParameterInference::operand(Domain::null(), false)->kind);
    }

    public function testInferSeparatesTheEventScheduleFromTheStoredBody(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-9.1.0');
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE AT ? DO SELECT ?, ?');
        $alter = $semantics->analyze('ALTER EVENT e DO SELECT ?, ?');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $create);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $alter);
        self::assertSame([0], array_keys(ParameterInference::infer($create)));
        self::assertSame([], ParameterInference::infer($alter));
    }

    public function testInitialKeepsWrittenPositionsBeforeSiblingInference(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT 1, ?, ? + NULL');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $nodes = (new \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterContexts())->expressions($statement);
        $types = ParameterInference::initial($statement, $nodes);

        self::assertCount(2, $types);
        self::assertSame([[Kind::String, false], [Kind::String, false]], [[$types[0][0]->kind, $types[0][1]], [$types[1][0]->kind, $types[1][1]]]);
        self::assertSame(65535, ParameterInference::infer($statement)[1][0]->length);
    }

    public function testInferPropagatesNumericContextsBeforeApplyingDefaults(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT ? / ?, -?, ? DIV ?, (? / ?) + 1, IF(1, ? / ?, 2), ? / (? + ?)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        self::assertSame([Kind::Double, Kind::Double, Kind::Double, Kind::Integer, Kind::Integer, Kind::Integer, Kind::Integer, Kind::Integer, Kind::Integer, Kind::Double, Kind::Double, Kind::Double], array_map(static fn (array $entry): Kind => $entry[0]->kind, ParameterInference::infer($statement)));
    }

    public function testMarkerUnwrapsOnlyWrappersThatPreserveTheInputSlot(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT (+?), -?');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        $first = $statement->field(0)->expression;
        $second = $statement->field(1)->expression;
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(0, ParameterInference::marker($first)?->position);
        self::assertNull(ParameterInference::marker($second));
        self::assertTrue(ParameterInference::pending($second));
        self::assertSame([1], array_keys(ParameterInference::propagate($second, Domain::double())));
    }

    public function testPendingDistinguishesActualBindingsFromUnboundPlaceholders(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([]);
        $settings = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults($context->profile->grammar);
        $statement = Semantics::fromStatement($context->withSession($settings->withParameters([Domain::integer(), Domain::integer()])), new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Divide, new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: 0), new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: 1)))])));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        self::assertSame([Kind::Integer, Kind::Integer], array_map(static fn (array $entry): Kind => $entry[0]->kind, ParameterInference::infer($statement)));
    }

    public function testContextsTakePrecedenceOverArithmeticDefaults(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT (? / ?) + 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $nodes = (new \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterContexts())->expressions($statement);
        self::assertSame(Kind::Integer, ParameterInference::contexts($statement, $nodes)[0][0]->kind);
        self::assertSame(Kind::Double, ParameterInference::defaults($nodes)[0][0]->kind);
    }

    public function testDefaultsUseIntegerDivisionAndDoubleNegation(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT ? DIV ?, -?');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $nodes = (new \SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterContexts())->expressions($statement);
        $domains = ParameterInference::defaults($nodes);
        self::assertSame([Kind::Integer, Kind::Integer, Kind::Double], [$domains[0][0]->kind, $domains[1][0]->kind, $domains[2][0]->kind]);
    }

    public function testPropagateKeepsReorderedSlotsAcrossUnresolvedNumericSubtrees(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT ? / (? + ?)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        $expression = $statement->field(0)->expression;
        self::assertNotNull($expression);
        self::assertSame([0, 1, 2], array_keys(ParameterInference::propagate($expression, Domain::double())));
    }

    public function testResolvedUsesKnownArithmeticSiblingsBeforeNumericFallbacks(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT (? + 1) + ?, ? / 2, -(? + 2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        $expression = $statement->field(0)->expression;
        self::assertNotNull($expression);
        self::assertSame(Kind::Integer, ParameterInference::resolved($expression)?->kind);
        self::assertSame([Kind::Integer, Kind::Integer, Kind::Integer, Kind::Integer], array_map(static fn (array $entry): Kind => $entry[0]->kind, ParameterInference::infer($statement)));
    }

}
