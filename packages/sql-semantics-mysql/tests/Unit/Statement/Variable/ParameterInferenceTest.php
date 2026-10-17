<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\ParameterInference;

#[CoversClass(ParameterInference::class)]
#[Small]
final class ParameterInferenceTest extends TestCase
{
    public function testInferKeepsMarkerOrderAndDerivesArithmeticAgainstNull(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ?, ? + NULL, NULL DIV ?, ? + 1, CAST(? AS SIGNED), ~?');
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
}
