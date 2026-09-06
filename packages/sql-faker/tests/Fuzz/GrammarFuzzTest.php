<?php

declare(strict_types=1);

namespace Tests\Integration\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Fuzz\Input\FuzzPlanDecoder;
use SqlFaker\Fuzz\Input\ProductionWitness;
use SqlFaker\Fuzz\Input\WitnessEncoder;
use SqlFaker\Fuzz\Run\FuzzCheckpoint;
use SqlFaker\Fuzz\Target\SqliteSyntaxCheck;
use SqlFaker\Fuzz\Target\SqlSyntaxTarget;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\LexicalGrammar;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversNothing]
final class GrammarFuzzTest extends TestCase
{
    /**
     * @return list<array{string, int}>
     */
    public static function providerAlternatives(): array
    {
        return [['stmt', 0], ['stmt', 1], ['expr', 0], ['expr', 1], ['tail', 0], ['tail', 1]];
    }

    #[DataProvider('providerAlternatives')]
    public function testEveryFeasibleProductionHasASeedExecutedThroughTheSameTarget(string $rule, int $ordinal): void
    {
        $grammar = CoverageFixture::syntaxGrammar();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $lexical->method('version')->willReturn('fixture');
        $lexical->method('realize')->willReturnCallback(CoverageFixture::realize(...));
        $coverage = new GrammarCoverage();
        $generator = new SqlGenerator($grammar, Factory::create(), $lexical, coverage: $coverage);
        $costs = new CompletionCosts($grammar, static fn (string $t): bool => true);
        $decoder = new FuzzPlanDecoder($costs->rule('stmt', true), 100);
        $witness = (new ProductionWitness($grammar, static fn (string $t): bool => true))->find('stmt', $rule, $ordinal);
        self::assertNotNull($witness);
        $input = (new WitnessEncoder($grammar, $costs, $decoder))->encode($witness);
        $directory = CoverageFixture::directory();
        $check = new SqliteSyntaxCheck();
        $target = new SqlSyntaxTarget($generator->generate(...), $decoder, $check->verify(...), new FuzzCheckpoint($coverage, $directory, []));
        $target($input);
        $id = $coverage->inventory()->id($rule, $grammar->ruleMap[$rule]->alternatives[$ordinal], $ordinal);
        $first = $coverage->snapshot();
        self::assertContains($id, $first['current']['reachedIds']);
        self::assertSame(1, $first['checkpoint']['generationsObservedInRun']);
        $target($input);
        $second = $coverage->snapshot();
        self::assertSame(2, $second['checkpoint']['generationsObservedInRun']);
        self::assertContains($id, $second['current']['emittedIds']);
        CoverageFixture::remove($directory);
    }

    public function testShortAndEmptyInputAlwaysDecodeToValidPlans(): void
    {
        $decoder = new FuzzPlanDecoder(2, 5000);
        self::assertSame(2, $decoder->decode('')->expansionBudget());
        self::assertSame(3, $decoder->decode("\x01")->expansionBudget());
        self::assertSame("\x01\x03", $decoder->decode("\0\0\0\0\x01\x02\x03")->structureBytes());
        self::assertSame("\x02", $decoder->decode("\0\0\0\0\x01\x02\x03")->lexicalBytes());
    }
}
