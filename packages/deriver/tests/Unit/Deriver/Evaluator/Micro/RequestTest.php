<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Micro;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Micro\Request;
use Deriver\Deriver\Evaluator\Request\Budget;
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
final class RequestTest extends TestCase
{
    public function testInputMemoizesADemandedNamedArgumentWithoutOpeningItsSibling(): void
    {
        $source = 'function target(){return call(unused:missing(),name:42);}';
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
        $calls = array_values(array_filter($frame->graph->definitions, static fn ($instruction): bool => $instruction->operation === 'invoke'));
        $request = new Request($engine, $frame, $calls[1], 20, '\CALL');
        self::assertSame('call', $request->name());
        self::assertSame([], $request->inputs());
        $first = $request->input('name');
        self::assertSame(42, $first->native());
        self::assertSame($first, $request->input('name'));
        self::assertSame(['name' => $first], $request->inputs());
        self::assertSame(0, $engine->context->referenceExpansions);
    }
    public function testNameNormalizesResolvedFunctionsWithoutChangingOperators(): void
    {
        $source = 'function target(){return 2+3;}';
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
        $operation = 'binary';
        $instruction1 = array_values(array_filter($frame->graph->definitions, static fn ($instruction1): bool => $instruction1->operation === $operation))[0] ?? null;
        $instruction = $instruction1;
        self::assertNotNull($instruction);
        self::assertSame('+', (new Request($engine, $frame, $instruction, 20))->name());
        self::assertSame('app\answer', (new Request($engine, $frame, $instruction, 20, '\App\ANSWER'))->name());
    }
    public function testInputsRetainsOnlyDemandedOperands(): void
    {
        $source = 'function target(){return missing()+3;}';
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
        $operation = 'binary';
        $instruction = array_values(array_filter($frame->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        self::assertNotNull($instruction);
        $request = new Request($engine, $frame, $instruction, 20);
        $right = $request->input(1);
        self::assertSame(3, $right->native());
        self::assertSame([1 => $right], $request->inputs());
        self::assertSame(0, $engine->context->referenceExpansions);
    }
    public function testOperationKeepsInstructionAndSiteDemandsDistinct(): void
    {
        $source = 'function subject(){return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $instruction = $graph->definitions[$graph->returns()[0][1]];
        $request = new Request($engine, $frame, $instruction, 32);
        self::assertSame('constant', $request->operation());
    }

    public function testSourceKeepsInstructionAndSiteDemandsDistinct(): void
    {
        $source = 'function subject(){return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $instruction = $graph->definitions[$graph->returns()[0][1]];
        $request = new Request($engine, $frame, $instruction, 32);
        self::assertSame($instruction->source, $request->source());
    }

    public function testInstructionKeepsInstructionAndSiteDemandsDistinct(): void
    {
        $source = 'function subject(){return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $instruction = $graph->definitions[$graph->returns()[0][1]];
        $request = new Request($engine, $frame, $instruction, 32);
        self::assertSame($instruction, $request->instruction());
    }

    public function testTruthKeepsInstructionAndSiteDemandsDistinct(): void
    {
        $source = 'function subject(){return +42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $instruction = $graph->definitions[$graph->returns()[0][1]];
        $request = new Request($engine, $frame, $instruction, 32);
        self::assertSame(true, $request->truth(0)->native());
        self::assertSame(42, $request->input(0)->native());
        self::assertCount(2, $request->inputs());
    }

    public function testRegisterRejectsAbsentInputsWithoutExpandingSource(): void
    {
        $source = 'function subject(){return +42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $request = new Request($engine, $frame, $graph->definitions[$graph->returns()[0][1]], 32);
        $this->expectException(\Deriver\Exception\EvaluationContractException::class);
        $request->register(4);
    }

}
