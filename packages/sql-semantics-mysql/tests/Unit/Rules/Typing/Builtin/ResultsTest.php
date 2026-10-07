<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Results;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Results::class)]
#[Small]
final class ResultsTest extends TestCase
{
    public function testRulesHoldEveryFamily(): void
    {
        self::assertArrayHasKey('CONCAT', Results::rules());
        self::assertArrayHasKey('PI', Results::rules());
        self::assertArrayHasKey('USER', Results::rules());
    }

    public function testTypeNeedsARuleAndResolvedArguments(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertEquals(Domain::double(8, 6), (new Results())->type('pi', [], [], $derivation));
        self::assertNull((new Results())->type('NO_SUCH_FUNCTION', [], [], $derivation));
        self::assertNull((new Results())->type('ABS', [new NumberLiteral('1')], [new Known(new Integral(IntegralKind::Int))], $derivation));
    }

    public function testRefineKeepsTheNullabilityOfTheFact(): void
    {
        $fact = new ScalarFact(new Known(new Integral(IntegralKind::BigInt)), Nullability::Nullable);

        self::assertEquals(new ScalarFact(new Known(Domain::double(8, 6)), Nullability::Nullable), (new Results())->refine('PI', [], [], $fact, new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }
}
