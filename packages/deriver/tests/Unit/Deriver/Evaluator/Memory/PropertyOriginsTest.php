<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Memory;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Invocation\Origin;
use Deriver\Deriver\Evaluator\Memory\PropertyOrigins;
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
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PropertyOriginsTest extends TestCase
{
    public function testInitialReadsOnlyTheDeclarationDefault(): void
    {
        $source = 'class Box{public $name="ledger";function target(){$this->name="changed";}}';
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
        $property = $engine->context->index->property('Box', 'name');
        self::assertNotNull($property);
        self::assertSame('ledger', (new PropertyOrigins($engine))->initial($property, 64)->native());
        self::assertSame([], $engine->context->bodies);
    }
    public function testValueAndWritesKeepCompoundStoredResults(): void
    {
        $source = 'class Box{public $name="ledger";function archive(){$this->name="ledger";$this->name.="_archive";}function target(){return $this->name;}}';
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
        $symbol = 'Box::target';
        $graph = $engine->context->index->graph($symbol);
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:' . $symbol);
        $property = $engine->context->index->property('Box', 'name');
        self::assertNotNull($property);
        $origins = new PropertyOrigins($engine);
        $operation = 'field-address';
        $instruction = array_values(array_filter($frame->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        self::assertNotNull($instruction);
        $result = $origins->value($frame, $instruction, $property, 64);
        $values = array_map(static fn (array $choice) => $choice[0]->native(), iterator_to_array((new Choices())->alternatives($result), false));
        self::assertSame(['ledger', 'ledger', 'ledger_archive'], $values);
        self::assertCount(3, $values);
        $symbol1 = 'Box::archive';
        $graph1 = $engine->context->index->graph($symbol1);
        self::assertNotNull($graph1);
        self::assertCount(2, $origins->writes($frame, (new Frame($graph1, 'source:' . $symbol1))->graph, $property, 64));
    }
    public function testWritesUsesTheReadBeforeEachMutation(): void
    {
        $source = 'class Box{public $n=1;function target(){$this->n=4;$this->n++;}}';
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
        $symbol = 'Box::target';
        $graph = $engine->context->index->graph($symbol);
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:' . $symbol);
        $property = $engine->context->index->property('Box', 'n');
        self::assertNotNull($property);
        $writes = (new PropertyOrigins($engine))->writes($frame, $frame->graph, $property, 64);
        self::assertSame(4, $writes[0][0]->native());
        self::assertSame(5, $writes[1][0]->native());
    }
    public function testSelectedWritesNeverEnterAReplacedBody(): void
    {
        $evaluator = new class (new CallDescriptor('change', '1', 'Box::change'), new SemanticPlan([])) implements CallEvaluator {
            /**
             * @param CallDescriptor $metadata Stable evaluator registration
             * @param SemanticPlan $plan Ordered API semantics
             */
            public function __construct(public readonly CallDescriptor $metadata, public readonly SemanticPlan $plan)
            {
            }
            /**
             * @return CallDescriptor Stable registration
             */
            #[Override]
            public function descriptor(): CallDescriptor
            {
                return $this->metadata;
            }
            /**
             * @param CallDescription $call Selected target
             * @return CallDecision Declarative fixture semantics
             */
            #[Override]
            public function evaluate(CallDescription $call): CallDecision
            {
                return CallDecision::handled($this->plan);
            }
        };
        $source = 'class Box{public $n=1;function change(){$this->n=9;}}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions(evaluators: [$evaluator]);
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
        $symbol = 'Box::change';
        $graph = $engine->context->index->graph($symbol);
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:' . $symbol);
        $property = $engine->context->index->property('Box', 'n');
        self::assertNotNull($property);
        [$inputs, $call] = (new Origin())->call($frame->graph);
        self::assertSame([], (new PropertyOrigins($engine))->selectedWrites($frame, $inputs, $call, 'Box::change', $property, 64));
        self::assertSame([], $engine->context->bodies);
    }
}
