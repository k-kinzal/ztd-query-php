<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Index;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
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
final class BodyIndexTest extends TestCase
{
    public function testReachableBlocksIncludesExceptionHandlerEntries(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('handlers.php', '<?php function target(){try{throw new Exception();}catch(Exception $e){return 1;}finally{return 2;}}')));
        $index = new DefinitionIndex(new Index('handlers', [$tree]));
        $graph = $index->graph('target');
        self::assertNotNull($graph);
        $region = $graph->body->regions[0];
        self::assertNotNull($region->finally);
        self::assertArrayHasKey($region->catches[0]->block, $graph->reachableBlocks());
        self::assertArrayHasKey($region->finally, $graph->reachableBlocks());
    }

    public function testReturnsIndexesAllReturnOriginsWithoutEvaluatingThem(): void
    {
        $source = 'function target($flag){if($flag){return 1;}return external();}';
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
        $f = new Frame($graph, 'source:' . $symbol);
        self::assertCount(2, $f->graph->returns());
        self::assertSame(0, $e->context->bodyExpansions);
    }
    public function testReturnsPreservesTheDefinitionBeforeFinally(): void
    {
        $source = 'function target(){try{return 1;}finally{return 2;}}';
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
        self::assertSame(2, (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget))[0]->result);
    }
    public function testWritesIncludesEveryDirectStorageMutation(): void
    {
        $source = 'function target(){$x=1;$x+=2;$x++;unset($x);}';
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
        $graph = $evaluator->context->index->graph($symbol);
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:' . $symbol);
        $operation = 'write';
        $instruction = array_values(array_filter($frame->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        self::assertNotNull($instruction);
        self::assertTrue(BodyIndex::writes($instruction));
        $operation1 = 'compound';
        $instruction1 = array_values(array_filter($frame->graph->definitions, static fn ($instruction1): bool => $instruction1->operation === $operation1))[0] ?? null;
        self::assertNotNull($instruction1);
        self::assertTrue(BodyIndex::writes($instruction1));
        $operation2 = 'increment';
        $instruction2 = array_values(array_filter($frame->graph->definitions, static fn ($instruction2): bool => $instruction2->operation === $operation2))[0] ?? null;
        self::assertNotNull($instruction2);
        self::assertTrue(BodyIndex::writes($instruction2));
        $operation3 = 'unset';
        $instruction3 = array_values(array_filter($frame->graph->definitions, static fn ($instruction3): bool => $instruction3->operation === $operation3))[0] ?? null;
        self::assertNotNull($instruction3);
        self::assertTrue(BodyIndex::writes($instruction3));
        $operation4 = 'local';
        $instruction4 = array_values(array_filter($frame->graph->definitions, static fn ($instruction4): bool => $instruction4->operation === $operation4))[0] ?? null;
        self::assertNotNull($instruction4);
        self::assertFalse(BodyIndex::writes($instruction4));
    }
    public function testRootFindsThePropertyBehindAnElementMutation(): void
    {
        $source = 'class Box{public $a=[1];function target(){$this->a[0]+=2;}}';
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
        $symbol = 'Box::target';
        $graph = $evaluator->context->index->graph($symbol);
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:' . $symbol);
        $operation = 'compound';
        $instruction = array_values(array_filter($frame->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        self::assertNotNull($instruction);
        self::assertSame('field-address', $frame->graph->root($instruction)?->operation);
    }
}
