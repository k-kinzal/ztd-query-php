<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Index;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\ControlDependencies;
use Deriver\Deriver\Evaluator\Index\ControlDependencies as Subject;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\TerminatorKind;
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
final class ControlDependenciesTest extends TestCase
{
    public function testBuildDoesNotControlAJoinWithIrrelevantBranches(): void
    {
        $source = 'function target($b){if($b){$x=1;}else{$x=2;}return 42;}';
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
        $frame = new Frame($graph, 'source:' . $symbol);
        $returns = $frame->graph->returns();
        self::assertSame([], (new Subject())->build($frame->graph)[$returns[0][0]] ?? []);
    }
    public function testPostdominatorsIncludesTheSingleExit(): void
    {
        $source = 'function target(){return 42;}';
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
        $evaluator = new Evaluator(new Context(new DefinitionIndex($program), $settings, $evaluators, $budget));
        $symbol = 'target';
        $graph1 = $evaluator->context->index->graph($symbol);
        self::assertNotNull($graph1);
        $graph = (new Frame($graph1, 'source:' . $symbol))->graph;
        self::assertSame([-1 => -1, 0 => -1], (new Subject())->postdominators($graph, null));
    }
    public function testOrderStartsAtTheVirtualExit(): void
    {
        $source = 'function target(){return 42;}';
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
        $evaluator = new Evaluator(new Context(new DefinitionIndex($program), $settings, $evaluators, $budget));
        $symbol = 'target';
        $graph1 = $evaluator->context->index->graph($symbol);
        self::assertNotNull($graph1);
        $graph = (new Frame($graph1, 'source:' . $symbol))->graph;
        self::assertSame([-1, 0], (new Subject())->order($graph));
    }
    public function testIntersectFindsTheNearestSharedAncestor(): void
    {
        self::assertSame(2, (new Subject())->intersect(0, 1, [-1 => -1, 2 => -1, 0 => 2, 1 => 2], [-1 => 0, 2 => 1, 0 => 2, 1 => 3]));
    }

    public function testInterleavesRecognizesOnlyEvaluationOrderBranches(): void
    {
        $source = new Source('order.php', '<?php function f($a){$r=strlen($a).$a;if($a){$r=1;}return $r;}');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('order', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $graph = $context->index->graph('f');
        self::assertNotNull($graph);
        $branches = array_values(array_filter($graph->body->blocks, static fn (BasicBlock $block): bool => $block->terminator->kind === TerminatorKind::Branch));
        $operands = array_map(static fn (BasicBlock $block): string => $block->terminator->operand, $branches);
        $operations = array_map(static fn (string $operand): string => $graph->definitions[$operand]->operation, $operands);
        $subject = new ControlDependencies();
        self::assertSame([true, true, true, false], array_map(static fn (string $operand): bool => $subject->interleaves($graph, $operand), $operands));
        self::assertSame(['evaluation-order', 'evaluation-order', 'evaluation-order'], array_slice($operations, 0, 3));
        self::assertFalse($subject->interleaves($graph, 'missing'));
    }
}
