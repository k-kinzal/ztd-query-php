<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationException;

#[CoversClass(GenerationException::class)]
final class GenerationExceptionTest extends TestCase
{
    public function testDerivationLimitExceededReportsTheBudget(): void
    {
        self::assertSame(
            'Exceeded derivation limit while generating SQL.',
            GenerationException::derivationLimitExceeded()->getMessage(),
        );
    }

    public function testUnknownRuleNamesTheMissingRule(): void
    {
        self::assertSame(
            'Unknown grammar rule: opt_where',
            GenerationException::unknownRule('opt_where')->getMessage(),
        );
    }

    public function testRuleHasNoAlternativesNamesTheEmptyRule(): void
    {
        self::assertSame(
            "Production rule 'statement' has no alternatives.",
            GenerationException::ruleHasNoAlternatives('statement')->getMessage(),
        );
    }

    public function testNoRealizableAlternativeNamesTheRule(): void
    {
        self::assertSame(
            'Grammar rule has no lexically realizable alternative: infinite',
            GenerationException::noRealizableAlternative('infinite')->getMessage(),
        );
    }

    public function testNoAlternativeMatchingPlanNamesTheRule(): void
    {
        self::assertSame(
            'Grammar rule has no alternative matching the generation plan: opt_values',
            GenerationException::noAlternativeMatchingPlan('opt_values')->getMessage(),
        );
    }

    public function testStartRuleCannotProduceOutputNamesTheStartRule(): void
    {
        self::assertSame(
            'Generation plan requires non-empty output, but the start rule cannot produce it: statement',
            GenerationException::startRuleCannotProduceOutput('statement')->getMessage(),
        );
    }

    public function testPlanRequiresNonEmptyOutputNamesTheDialect(): void
    {
        self::assertSame(
            'MySQL generation plan requires non-empty output.',
            GenerationException::planRequiresNonEmptyOutput('MySQL')->getMessage(),
        );
    }

    public function testLexicalRealizationFailedNamesTheDialect(): void
    {
        self::assertSame(
            'SQLite lexical realization failed.',
            GenerationException::lexicalRealizationFailed('SQLite')->getMessage(),
        );
    }
}
