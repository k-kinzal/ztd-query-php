<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Expression;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Expression\Origins;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\CallObservations;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OriginsTest extends TestCase
{
    public function testParameterRetainsExternalInputThroughSameNamedForwardingMethods(): void
    {
        $source = new Source('forwarding.php', '<?php
            class Service { public function create(string $input): string { return $input; } }
            class Controller { public function create(Service $service, string $input): string { return $service->create($input); } }
        ');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('forwarding', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $graph = $context->index->graph('Service::create');
        self::assertNotNull($graph);

        $result = (new Evaluator($context))->returns(new Frame($graph, 'source:Service::create'), 64);
        $rows = iterator_to_array((new Choices())->alternatives($result), false);

        self::assertCount(1, $rows);
        self::assertSame('$input', $rows[0][0]->literal);
        self::assertSame('EXTERNAL_INPUT', $rows[0][0]->attributes['reason']);
        self::assertSame('Controller::create', $rows[0][0]->attributes['scope']);
    }

    public function testCallersReturnsNullWhenOnlyUnrelatedTypedReceiversCallTheMethodName(): void
    {
        $source = new Source('unrelated.php', '<?php
            class Controller { public function create(string $input): string { return $input; } }
            class Service { public function create(string $input): string { return $input; } }
            function caller(Service $service) { return $service->create("service input"); }
        ');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('unrelated', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $graph = $context->index->graph('Controller::create');
        self::assertNotNull($graph);
        self::assertSame([], $context->index->callers('Controller::create'));

        self::assertNull((new Origins(new Evaluator($context)))->callers(new Frame($graph, 'source:Controller::create'), 'input', 64));
    }

    public function testCallersPreservesAnEmptyArgumentFromAMatchingCall(): void
    {
        $source = new Source('empty.php', '<?php function target($input) { return $input; } function caller($input) { return target($input); }');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('empty', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $graph = $context->index->graph('target');
        $caller = $context->index->graph('caller');
        self::assertNotNull($graph);
        self::assertNotNull($caller);
        $context->entryFrames['caller'] = [new Frame($caller, 'entry:caller', ['input' => new Term(TermKind::Choice)], invocation: true)];

        $result = (new Origins(new Evaluator($context)))->callers(new Frame($graph, 'source:target'), 'input', 64);

        self::assertNotNull($result);
        self::assertSame([], iterator_to_array((new Choices())->alternatives($result), false));
    }

    public function testCallersKeepsMatchingActualsWhenOtherClassesUseTheSameMethodName(): void
    {
        $source = new Source('matching.php', '<?php
            class Controller { public function create(string $input): string { return $input; } }
            class Service { public function create(string $input): string { return $input; } }
            function caller(Service $service, Controller $controller) { $service->create("service input"); return $controller->create("controller input"); }
        ');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('matching', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $graph = $context->index->graph('Controller::create');
        self::assertNotNull($graph);

        $result = (new Evaluator($context))->returns(new Frame($graph, 'source:Controller::create'), 64);
        $rows = iterator_to_array((new Choices())->alternatives($result), false);

        self::assertCount(1, $rows);
        self::assertSame('controller input', $rows[0][0]->native());
    }

    public function testParameterPreservesAnUnboundInput(): void
    {
        $source = 'function target(int $x){return $x;}';
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
        $r = $e->returns(new Frame($graph, 'source:' . $symbol), 64);
        self::assertSame('$x', $r->literal);
        self::assertSame('int', $r->attributes['type']);
    }
    public function testCallersKeepsActualsCorrelated(): void
    {
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . 'function target($x){observe($x);}function caller(){target(1);target(2);}')]);
        $prepared1Configuration = new DeriverConfig();
        $prepared1Contributions = new Contributions();
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $s = new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot);
        $result = $s->derive(new ValueQuery((new CallObservations($prepared1Program, $prepared1Evaluators))->find('observe')[0]->argument(0)));
        $slot = 'value';
        self::assertSame([1, 2], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($result, false))));
    }
    public function testPropertyCollectsOrdinaryWrites(): void
    {
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . 'class B{public $x=1;function set(){$this->x=2;}function get(){observe($this->x);}}')]);
        $prepared2Configuration = new DeriverConfig();
        $prepared2Contributions = new Contributions();
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $s = new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot);
        $result = $s->derive(new ValueQuery((new CallObservations($prepared2Program, $prepared2Evaluators))->find('observe')[0]->argument(0)));
        $slot = 'value';
        self::assertSame([1, 2], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($result, false))));
    }
    public function testUnboundKeepsTheDeclaredInputType(): void
    {
        $source = 'function target(int $x){return $x;}';
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
        $operation = 'local';
        $instruction = array_values(array_filter($f->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        self::assertNotNull($instruction);
        $v = (new Origins($e))->unbound($f, $instruction, 'int', true);
        self::assertSame('int', $v->attributes['type']);
        self::assertSame('EXTERNAL_INPUT', $v->attributes['reason']);
    }
    public function testBindingEvidenceRetainsActualAndFormalRelation(): void
    {
        $source = 'function target($x){return $x;}function caller(){return target(9);}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared3Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared3Configuration = $configuration;
        $prepared3Contributions = $contributions;
        $prepared3Target = new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision);
        $prepared3Providers = new ProviderExpansion($prepared3Input, array_map(Term::fromNative(...), $prepared3Configuration->environment), $prepared3Contributions);
        $contributions1 = $prepared3Providers->contributions;
        $prepared3Evaluators = new Evaluators(new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared3Configuration->standardEvaluators);
        $prepared3Settings = new EvaluatorConfig($prepared3Target, $prepared3Providers->environment, $prepared3Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared3Configuration->evaluators));
        $prepared3Identity = new SnapshotIdentity($prepared3Providers->input, $prepared3Settings, $prepared3Evaluators, $prepared3Providers->manifest);
        $snapshotId = $prepared3Identity->id;
        $input = $prepared3Providers->input;
        $parser = new Parser(new ParserConfig($prepared3Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared3Program = new Index($snapshotId, $trees);
        $prepared3Snapshot = $prepared3Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared3Program->diagnostics()));
        $result = (new CandidateQuery(new DefinitionIndex($prepared3Program), $prepared3Settings, $prepared3Evaluators, $prepared3Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertSame(9, $result->forCaller('caller')[0]->result);
        self::assertContains(EvidenceKind::ArgumentBinding, array_column($result[0]->evidence[0]->nodes(), 'kind'));
    }

    public function testDynamicSymbolsDetectsVariableVariablesIncludeAndExtract(): void
    {
        $source = new Source('dynamic.php', '<?php function plain(){$a=1;return $a;} function variable($n){$$n=1;} function included(){include "x.php";} function extracted($r){extract($r);}');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('dynamic', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $origins = new Origins(new Evaluator($context));
        $graph = static fn (string $symbol): BodyIndex => $context->index->graph($symbol) ?? throw new LogicException('Missing graph.');
        self::assertFalse($origins->dynamicSymbols($graph('plain')));
        self::assertTrue($origins->dynamicSymbols($graph('variable')));
        self::assertTrue($origins->dynamicSymbols($graph('included')));
        self::assertTrue($origins->dynamicSymbols($graph('extracted')));
    }

    public function testPropertyOfReadsTheDeclaredDefaultOfOneReceiver(): void
    {
        $source = new Source('property.php', '<?php class A{public $p="v";} function f(){$a=new A();return $a->p;}');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('property', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $graph = $context->index->graph('f');
        self::assertNotNull($graph);
        $fields = array_values(array_filter($graph->definitions, static fn (Instruction $instruction): bool => $instruction->operation === 'field-address'));
        self::assertCount(1, $fields);
        $engine = new Evaluator($context);
        $frame = new Frame($graph, 'source:f');
        $receiver = $engine->value($frame, $fields[0]->operands[0], 64);
        self::assertSame(TermKind::Object, $receiver->kind);
        self::assertSame('v', (new Origins($engine))->propertyOf($frame, $fields[0], 'p', $context->index->declaredProperty($graph, $fields[0]), $receiver, 64)->literal);
    }
}
