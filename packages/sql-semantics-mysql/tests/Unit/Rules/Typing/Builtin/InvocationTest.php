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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Invocation::class)]
#[Small]
final class InvocationTest extends TestCase
{
    public function testDomainAnswersNullBeyondTheArguments(): void
    {
        self::assertEquals(Domain::null(), (new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->domain(0));
    }

    public function testConstantReadsIntegerAndDigitLiterals(): void
    {
        $call = new Invocation([], [new NumberLiteral('12'), new Unary(UnaryOperator::Minus, new NumberLiteral('3')), new StringLiteral(['4x']), new NumberLiteral('1.5')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([12, -3, 4, null], [$call->constant(0), $call->constant(1), $call->constant(2), $call->constant(3)]);
    }

    public function testConstantClampsIntegersBeyondTheRangeOfAnInt(): void
    {
        $call = new Invocation([], [new NumberLiteral('18446744073709551615'), new Unary(UnaryOperator::Minus, new NumberLiteral('9223372036854775808')), new NumberLiteral('0009'), new StringLiteral(['99999999999999999999x'])], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([PHP_INT_MAX, -PHP_INT_MAX, 9, PHP_INT_MAX], [$call->constant(0), $call->constant(1), $call->constant(2), $call->constant(3)]);
    }

    public function testLengthWritesNumbersAsText(): void
    {
        self::assertSame([22, 0, 4], [(new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->length(Domain::double()), (new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->length(Domain::null()), (new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->length(Domain::integer(Field::LongLong, 4))]);
    }

    public function testTextBecomesAMediumBlobBeyondSixteenThousandUtf8mb4Characters(): void
    {
        self::assertSame(Field::VarString, (new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->text([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], 16383, 'concat')?->field);
        self::assertSame(Field::MediumBlob, (new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->text([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], 16384, 'concat')?->field);
    }

    public function testCollationsUseTheConnection(): void
    {
        self::assertSame('utf8mb4_0900_ai_ci', (new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->collations()->connection->name);
    }

    public function testAggregationUsesTheConnection(): void
    {
        self::assertSame('utf8mb4_0900_ai_ci', (new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))->aggregation()->collations->connection->name);
    }

    public function testLengthTakesTheDisplayLengthOfAFloat(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([12, 22], [$call->length(new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED)), $call->length(Domain::double(5))]);
    }

    public function testTextIsAMediumBlobBeyond65535Bytes(): void
    {
        $call = new Invocation([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals([Domain::string(16383, Collation::known('utf8mb4_0900_ai_ci')), Domain::string(65536, Collation::known('utf8mb4_0900_ai_ci'), Field::MediumBlob)], [$call->text($call->domains, 16383, 'repeat'), $call->text($call->domains, 16384, 'repeat')]);
    }

    public function testLengthTakesTheDisplayLengthOfADoubleInMySql57(): void
    {
        $query = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT CONCAT(1e0), CONCAT(1e0 + 1)');
        $literal = $query->field(0)->type;
        $computed = $query->field(1)->type;

        self::assertInstanceOf(Known::class, $literal);
        self::assertInstanceOf(Known::class, $computed);
        self::assertInstanceOf(Domain::class, $literal->descriptor);
        self::assertInstanceOf(Domain::class, $computed->descriptor);
        self::assertSame([3, 23], [$literal->descriptor->length, $computed->descriptor->length]);
    }
}
