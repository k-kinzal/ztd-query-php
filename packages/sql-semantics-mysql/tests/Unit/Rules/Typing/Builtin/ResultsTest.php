<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Results;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
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
        self::assertArrayHasKey('REGEXP_REPLACE', Results::rules());
    }

    public function testRefineMakesARegexpReplacementOfMultibyteCharactersNullable(): void
    {
        $fact = new ScalarFact(new Known(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))), Nullability::NotNull);
        $text = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(Nullability::Nullable, (new Results())->refine('REGEXP_REPLACE', [], [new ScalarFact(new Known($text), Nullability::NotNull), new ScalarFact(new Known($text), Nullability::NotNull), new ScalarFact(new Known($text), Nullability::NotNull)], $fact, new Derivation((new Semantics(Dialect::MySql))->context([])))->nullability);
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

    public function testRefineMakesTheAccountFunctionsNotNullIn56(): void
    {
        $legacy = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]));
        $modern = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $fact = new ScalarFact(new Known(Domain::double(23)), Nullability::Nullable);

        self::assertSame([Nullability::NotNull, Nullability::Nullable], [(new Results())->refine('USER', [], [], $fact, $legacy)->nullability, (new Results())->refine('USER', [], [], $fact, $modern)->nullability]);
    }

    /**
     * @return iterable<string, array{string, Nullability}>
     */
    public static function providerLegacyNullability(): iterable
    {
        yield '5.6' => ['mysql-5.6.51', Nullability::NotNull];
        yield '5.7' => ['mysql-5.7.44', Nullability::Nullable];
    }

    #[DataProvider('providerLegacyNullability')]
    public function testRefineRetainsLegacyCoalesceAndIntrospectionNullability(string $release, Nullability $introspection): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, $release))->context([]));
        $null = new ScalarFact(new Known(Domain::null()), Nullability::Nullable);
        $number = new ScalarFact(new Known(Domain::integer()), Nullability::NotNull);

        self::assertSame(Nullability::Nullable, (new Results())->refine('COALESCE', [], [$null, $number], $number, $derivation)->nullability);
        self::assertSame($introspection, (new Results())->refine('COLLATION', [], [$null], $null, $derivation)->nullability);
        self::assertSame($introspection, (new Results())->refine('CHARSET', [], [$null], $null, $derivation)->nullability);
    }

    public function testPropagatedAnswersMostStringFunctionsAsNotNullOfNotNullArgumentsInMySql56(): void
    {
        $query = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("SELECT CONCAT('a'), REPEAT('a', 2), LOWER(NULL)");

        self::assertSame([Nullability::NotNull, Nullability::NotNull, Nullability::Nullable], [$query->field(0)->nullability, $query->field(1)->nullability, $query->field(2)->nullability]);
    }
}
