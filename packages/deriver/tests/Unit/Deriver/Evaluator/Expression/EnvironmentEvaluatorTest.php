<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Expression\EnvironmentEvaluator;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EnvironmentEvaluatorTest extends TestCase
{
    public function testEvaluateReturnsExplicitNamedInputs(): void
    {
        $value = Term::constant('test');
        $absent = Term::constant(false);
        $evaluator = new EnvironmentEvaluator(['APP_ENV' => $value, 'ABSENT' => $absent]);
        self::assertSame($value, $evaluator->evaluate([Term::constant('APP_ENV')]));
        self::assertSame($value, $evaluator->evaluate([Term::constant('APP_ENV'), Term::constant(true)]));
        self::assertSame($absent, $evaluator->evaluate([Term::constant('ABSENT')]));
    }

    /**
     * @param list<Term> $arguments Arguments that cannot select an explicit environment name
     */
    #[DataProvider('providerUnresolvedLookups')]
    public function testEvaluateLeavesMissingAndWholeEnvironmentLookupsUnresolved(array $arguments): void
    {
        $evaluator = new EnvironmentEvaluator(['APP_ENV' => Term::constant('test'), '$_GET' => Term::fromNative(['id' => 1])]);
        self::assertNull($evaluator->evaluate($arguments));
    }

    /**
     * @return iterable<string, array{list<Term>}>
     */
    public static function providerUnresolvedLookups(): iterable
    {
        yield 'missing name' => [[Term::constant('MISSING')]];
        yield 'variable key' => [[Term::constant('$_GET')]];
        yield 'whole environment' => [[]];
        yield 'null name' => [[Term::constant(null)]];
        yield 'unknown name' => [[Term::parameter('name', 'string')]];
    }
}
