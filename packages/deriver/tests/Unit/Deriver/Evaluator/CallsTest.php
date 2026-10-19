<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator;

use Deriver\Deriver\Candidate;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Calls;
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
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use WeakMap;

#[CoversNothing]
final class CallsTest extends TestCase
{
    public function testValueExpandsOnlyADemandedCall(): void
    {
        $source = 'function one(){return 1;}function target(){return one()+2;}';
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
        self::assertSame(3, $resolvedValue->native());
    }
    public function testCapturesReadTheCreationDefinition(): void
    {
        $source = 'function target(){$x=7;$f=function()use($x){return $x;};$x=9;return $f();}';
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
        self::assertSame(7, $resolvedValue->native());
    }
    public function testPassThroughRecursionClosesOnlyProvenUnchangedBindings(): void
    {
        $source = 'function target($x){return target($x);}';
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
        self::assertSame(TermKind::Operation, $r->kind);
        self::assertNotEmpty($r->operands);
    }
    public function testBindKeepsNamedArgumentsLazy(): void
    {
        $source = 'function named($a,$b){return $a;}function target(){return named(b:missing(),a:4);}';
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
        self::assertSame(4, $resolvedValue->native());
    }
    public function testSelectedEffectUpdatesOnlyAReferenceArgument(): void
    {
        $source = 'function change(&$x){$x=8;}function target(){$x=1;change($x);return $x;}';
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
        self::assertSame(8, $resolvedValue->native());
        self::assertSame(['change' => 1], $e->context->bodies);
    }
    public function testFinalStorageKeepsTheLastWriteAtEachReturn(): void
    {
        $source = 'function change(&$x){$x=2;$x=3;return 7;}function target(){$x=1;change($x);return $x;}';
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
        self::assertSame(3, $resolvedValue->native());
    }
    public function testExpandResolvesAnAlreadySelectedTarget(): void
    {
        $source = 'function one(){return 1;}function target(){return one();}';
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
        $operation = 'invoke';
        $instruction = array_values(array_filter($f->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        $call = $instruction;
        self::assertNotNull($call);
        self::assertSame(1, (new Calls($e))->expand($f, $call, 'one', null, 64)->native());
    }
    public function testSignatureKeepsTypeErrorsIndependentOfTheBody(): void
    {
        $source = 'function one(int $x){return 1;}function target(){return one([]);}';
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
        $operation = 'invoke';
        $instruction = array_values(array_filter($f->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        $call = $instruction;
        self::assertNotNull($call);
        $symbol1 = 'one';
        $graph1 = $e->context->index->graph($symbol1);
        self::assertNotNull($graph1);
        $error = (new Calls($e))->signature($f, $call, (new Frame($graph1, 'source:' . $symbol1))->graph, 64);
        self::assertNotNull($error);
        self::assertSame('TypeError', $error->literal);
    }
    public function testUnknownWritePreservesAReadBeforeTheCall(): void
    {
        $source = 'function target(){$x=3;missing($x);return $x;}';
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
        $operation = 'invoke';
        $instruction = array_values(array_filter($f->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        $call = $instruction;
        self::assertNotNull($call);
        $operation1 = 'local';
        $instruction1 = array_values(array_filter($f->graph->definitions, static fn ($instruction1): bool => $instruction1->operation === $operation1))[0] ?? null;
        $a = $instruction1;
        self::assertNotNull($a);
        $v = (new Calls($e))->unknownWrite($f, $call, $a->result, 64);
        self::assertNotNull($v);
        self::assertSame(3, $v->operands[0]->literal);
    }
    public function testEffectUsesTheConcreteReceiverForReferenceWrites(): void
    {
        $source = 'class A{function change(&$x){$x=1;}}class B extends A{function change(&$x){$x=7;}}function apply(A $b){$x=0;$b->change($x);return $x;}function target(){return apply(new B);}';
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
        self::assertSame(7, $resolvedValue->native());
        self::assertArrayNotHasKey('A::change', $e->context->bodies);
    }
    public function testPassedIgnoresStorageNotPassedToTheCall(): void
    {
        $source = 'function target(){$safe=3;missing(1);return $safe;}';
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
        $operation = 'invoke';
        $instruction = array_values(array_filter($f->graph->definitions, static fn ($instruction): bool => $instruction->operation === $operation))[0] ?? null;
        $operation1 = 'local';
        $instruction1 = array_values(array_filter($f->graph->definitions, static fn ($instruction1): bool => $instruction1->operation === $operation1))[0] ?? null;
        self::assertNotNull($instruction);
        self::assertNotNull($instruction1);
        self::assertNull((new Calls($e))->passed($f, $instruction, $instruction1->result));
    }
    public function testStableEnvironmentRejectsMutableObjectInputs(): void
    {
        $source = 'class Box{public $n=4;}function target(Box $box){$box->n--;return target($box);}';
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
        self::assertFalse((new Calls($e))->stableEnvironment($f));
    }
    public function testInvokePreservesCallDependencies(): void
    {
        $source = 'function f($x){return $x+1;}function target(){return f(4);}';
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
        $result = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertSame(5, $result[0]->result);
    }
    public function testMissingPreservesCallDependencies(): void
    {
        $source = 'function target(){return external(1);}';
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
        $result = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertSame(CandidateStatus::Partials, $result[0]->type);
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source2 = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source2, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertContains('MISSING_SOURCE', array_column($frontiers, 'code'));
    }

    public function testByValueRecognizesStandardParametersPassedByValue(): void
    {
        $source = new Source('calls.php', '<?php function up($v){return $v;} function f($a){strlen($a);sort($a);up($a);}');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $context = new Context(new DefinitionIndex(new Index('calls', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget());
        $graph = $context->index->graph('f');
        self::assertNotNull($graph);
        $calls = array_values(array_filter($graph->definitions, static fn (Instruction $instruction): bool => $instruction->operation === 'invoke'));
        self::assertCount(3, $calls);
        $subject = new Calls(new Evaluator($context));
        self::assertTrue($subject->byValue($calls[0], 'strlen', $calls[0]->arguments[0]->register));
        self::assertFalse($subject->byValue($calls[1], 'sort', $calls[1]->arguments[0]->register));
        self::assertFalse($subject->byValue($calls[2], 'up', $calls[2]->arguments[0]->register));
        self::assertFalse($subject->byValue($calls[0], 'strlen', 'missing'));
    }
}
