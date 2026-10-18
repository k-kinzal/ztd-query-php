<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Language;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Language\Catches;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CatchesTest extends TestCase
{
    public function testResolveFollowsTheCatchSelectedByADemandedValue(): void
    {
        $source = 'function number(int $x){return $x;}function target(){try{return number([]);}catch(TypeError $e){return "typed";}}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion(new Sources([new Source('candidate.php', '<?php ' . $source)]), array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $snapshotId = 'test';
        $input = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $program = new Index($snapshotId, $trees);
        $e = new Evaluator(new Context(new DefinitionIndex($program), $settings, $evaluators, $budget));
        $symbol = 'target';
        $graph = $e->context->index->graph($symbol);
        self::assertNotNull($graph);
        $alternatives = (new Choices())->alternatives($e->returns(new Frame($graph, 'source:' . $symbol), 64));
        $values = array_reduce(iterator_to_array($alternatives, false), static function (array $values, array $alternative): array {
            [$value, $_] = $alternative;
            $values[(new Identity())->key($value)] = $value;
            return $values;
        }, []);
        self::assertCount(1, $values);
        $resolvedValue = array_values($values)[0];
        self::assertInstanceOf(Term::class, $resolvedValue);
        self::assertSame('typed', $resolvedValue->native());
    }
    public function testRegionsIndexesTheLexicalHandler(): void
    {
        $source = 'function target(){try{return missing();}catch(Error $e){return 1;}}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion(new Sources([new Source('candidate.php', '<?php ' . $source)]), array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $snapshotId = 'test';
        $input = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $program = new Index($snapshotId, $trees);
        $e = new Evaluator(new Context(new DefinitionIndex($program), $settings, $evaluators, $budget));
        $symbol = 'target';
        $graph = $e->context->index->graph($symbol);
        self::assertNotNull($graph);
        self::assertSame([0], (new Catches())->regions(new Frame($graph, 'source:' . $symbol), 0));
    }
}
