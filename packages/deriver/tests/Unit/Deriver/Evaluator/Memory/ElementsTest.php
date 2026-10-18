<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Memory;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
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
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ElementsTest extends TestCase
{
    public function testWritePreservesUnchangedSiblings(): void
    {
        $source = 'function target(){$a=["x"=>["y"=>1,"z"=>2]];$a["x"]["y"]=3;return $a;}';
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
        $engine = new Evaluator(new Context(new DefinitionIndex($program), $settings, $evaluators, $budget));
        $symbol = 'target';
        $graph = $engine->context->index->graph($symbol);
        self::assertNotNull($graph);
        $alternatives = (new Choices())->alternatives($engine->returns(new Frame($graph, 'source:' . $symbol), 64));
        $values = array_reduce(iterator_to_array($alternatives, false), static function (array $values, array $alternative): array {
            [$value, $_] = $alternative;
            $values[(new Identity())->key($value)] = $value;
            return $values;
        }, []);
        self::assertCount(1, $values);
        $resolvedValue = array_values($values)[0];
        self::assertInstanceOf(Term::class, $resolvedValue);
        self::assertSame(['x' => ['y' => 3, 'z' => 2]], $resolvedValue->native());
    }
    public function testMutatePreservesNestedCompoundAndUnset(): void
    {
        $source = 'function target(){$a=["x"=>["y"=>1,"z"=>9]];$a["x"]["y"]+=2;unset($a["x"]["z"]);return $a;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        self::assertSame(['x' => ['y' => 3]], (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget))[0]->result);
    }
}
