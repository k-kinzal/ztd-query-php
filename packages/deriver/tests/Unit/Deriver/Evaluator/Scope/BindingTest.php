<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Scope;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Binding;
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
final class BindingTest extends TestCase
{
    public function testBindingRetainsTheCallerAndUnevaluatedRegister(): void
    {
        $source = 'function target($x){return $x+2;}';
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
        $binding = new Binding($f, 'r0');
        self::assertSame($f, $binding->frame);
        self::assertSame(0, $e->context->referenceExpansions);
    }
    public function testValueAppliesTheCallingScalarModeToADemandedArgument(): void
    {
        $source = 'function target(){return "7";}';
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
        $frame = new Frame($graph, 'source:' . $symbol);
        $operation = 'constant';
        $instruction1 = array_values(array_filter($frame->graph->definitions, static fn ($instruction1): bool => $instruction1->operation === $operation))[0] ?? null;
        $instruction = $instruction1;
        self::assertNotNull($instruction);
        $binding = new Binding($frame, $instruction->result);
        self::assertSame(7, $binding->value($e, 'int', 64)->native());
    }
    public function testTruthRetainsTheOriginalValueBinding(): void
    {
        $source = 'function subject(){return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $binding = new Binding($frame, $graph->returns()[0][1]);
        self::assertSame(true, $binding->truth($engine, 'mixed', 32)->native());
        self::assertSame(42, $binding->value($engine, 'mixed', 32)->native());
    }

    public function testFiniteDoesNotTruncateAnOrdinaryValueRead(): void
    {
        $source = 'function subject($x){return $x?0:($x?1:2);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $binding = new Binding($frame, $graph->returns()[0][1]);
        $finite = $binding->finite($engine, 'mixed', 32, \Deriver\Deriver\Evaluator\Enumeration\Finite\Demand::truth());
        $full = $binding->value($engine, 'mixed', 32);
        self::assertSame([0,1], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new \Deriver\Deriver\Evaluator\Enumeration\Choices())->alternatives($finite))));
        self::assertSame([0,1,2], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new \Deriver\Deriver\Evaluator\Enumeration\Choices())->alternatives($full))));
    }
}
