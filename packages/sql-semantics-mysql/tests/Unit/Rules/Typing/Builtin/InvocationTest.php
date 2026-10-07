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
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Invocation::class)]
#[Small]
final class InvocationTest extends TestCase
{
    public function testDomainAnswersNullBeyondTheArguments(): void
    {
        self::assertEquals(Domain::null(), new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->domain(0));
    }

    public function testConstantReadsIntegerAndDigitLiterals(): void
    {
        $call = new Invocation([], [new NumberLiteral('12'), new Unary(UnaryOperator::Minus, new NumberLiteral('3')), new StringLiteral(['4x']), new NumberLiteral('1.5')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([12, -3, 4, null], [$call->constant(0), $call->constant(1), $call->constant(2), $call->constant(3)]);
    }

    public function testLengthWritesNumbersAsText(): void
    {
        self::assertSame([22, 0, 4], [new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->length(Domain::double()), new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->length(Domain::null()), new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->length(Domain::integer(Field::LongLong, 4))]);
    }

    public function testTextBecomesABlobBeyondSixteenThousandCharacters(): void
    {
        self::assertSame(Field::VarString, new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->text([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], 16383, 'concat')?->field);
        self::assertSame(Field::Blob, new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->text([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], 16384, 'concat')?->field);
    }

    public function testCollationsUseTheConnection(): void
    {
        self::assertSame('utf8mb4_0900_ai_ci', new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->collations()->connection->name);
    }

    public function testAggregationUsesTheConnection(): void
    {
        self::assertSame('utf8mb4_0900_ai_ci', new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))->aggregation()->collations->connection->name);
    }
}
