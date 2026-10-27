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
    public function testInferKeepsMarkerOrderAndDerivesArithmeticAgainstNull(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ?, ? + NULL, NULL DIV ?, ? + 1, CAST(? AS SIGNED), ~?');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $types = ParameterInference::infer($operation);

        self::assertSame([0, 1, 2, 3, 4, 5], array_keys($types));
        self::assertSame([[Kind::String, false], [Kind::Double, false], [Kind::Double, false], [Kind::Integer, false], [Kind::String, true], [Kind::String, false]], array_map(static fn (array $entry): array => [$entry[0]->kind, $entry[1]], $types));
        self::assertSame(65535, $types[5][0]->length);
        self::assertTrue($types[5][0]->collation->bytes());
    }

    public function testOperandWidensIntegerInputsAndPreservesOtherResolvedDomains(): void
    {
        $decimal = Domain::decimal(5, 2);

        self::assertSame(21, ParameterInference::operand(Domain::integer(length: 2), true)->length);
        self::assertSame($decimal, ParameterInference::operand($decimal, false));
        self::assertSame(Kind::Double, ParameterInference::operand(Domain::null(), true)->kind);
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
        self::assertSame(Kind::Double, ParameterInference::infer($statement)[1][0]->kind);
    }

}
