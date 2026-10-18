<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Declaration\Parameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Intrinsic\IntrinsicDescriptor;
use Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ExecutionValueTest extends TestCase
{
    public function testDerivedReturnsPreserveSuppliedInputs(): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function target($input){return "prefix:".$input;}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('input-value')])]));
        $result = $session->derive($query);
        self::assertSame('prefix:input-value', $result->normalOutcomes[0]->values['return']->native());
    }
    public function testNonUtf8BytesAndFloatingPointSpecialValuesRemainExact(): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function target($input){return $input;}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::array(['bytes' => Term::constant("\xff\x00"), 'infinity' => Term::constant(INF), 'negative-zero' => Term::constant(-0.0)])])]));
        $value = $session->derive($query)->normalOutcomes[0]->values['return'];
        self::assertSame("\xff\x00", $value->operands['bytes']->native());
        self::assertSame(INF, $value->operands['infinity']->native());
        $zero = $value->operands['negative-zero']->native();
        self::assertIsFloat($zero);
        self::assertSame('8000000000000000', bin2hex(pack('E', $zero)));
    }
    public function testNonUtf8IdentifierMetadataRemainsExact(): void
    {
        $source = "<?php function target(){return \xe8;}";
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame("constant:\xe8", $result->frontiers[0]->operation);
    }
    public function testStorageGraphPreservesReturnedObjectCyclesAndLocalAliases(): void
    {
        $source = '<?php class B{public ?B $self=null;public int $value=3;} function target(){$b=new B;$alias=$b;$b->self=$b;$x=1;$y=&$x;return $b;}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity1 = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity1->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity1->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        $outcome = $result->normalOutcomes[0];
        $identity = $outcome->values['return']->literal;
        self::assertIsString($identity);
        self::assertSame($identity, $outcome->storage->cells['object:' . $identity]->operands['self']->literal);
        self::assertSame(3, $outcome->storage->cells['object:' . $identity]->operands['value']->native());
        self::assertSame($outcome->storage->bindings['x']->literal, $outcome->storage->bindings['y']->literal);
        self::assertSame(TermKind::Object, $outcome->values['return']->kind);
    }
    public function testExceptionalStorageIncludesPropertiesWrittenBeforeTheThrow(): void
    {
        $source = '<?php class B{public int $value=1;} function target(){$b=new B;$b->value=4;throw new Error("failure");}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity1 = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity1->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity1->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        $outcome = $result->exceptionalOutcomes[0];
        $identity = $outcome->state['b']->literal;
        self::assertIsString($identity);
        self::assertSame(4, $outcome->storage->cells['object:' . $identity]->operands['value']->native());
        self::assertNotEmpty($outcome->evidence);
    }
    public function testCustomIntrinsicResultsPreserveReturnedValues(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('example.custom', '1', 'example.custom', 1, [0]));
        $intrinsic->method('evaluate')->willReturn(Term::constant('derived-value'));
        $signature = new Signature([new Parameter('value', 'string')]);
        $plan = new SemanticPlan([Action::returns(new Expression(ExpressionKind::Intrinsic, 'example.custom', [Expression::parameter('value')]))]);
        $evaluator = new class (new CallDescriptor('example.custom', '1', 'derive_custom', $signature), $plan) implements CallEvaluator {
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
        $input = new Sources([new Source('fixture.php', '<?php function target(string $input){return derive_custom($input);}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions(evaluators: [$evaluator], intrinsics: [$intrinsic]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('input-value')])])));
        self::assertSame([], $result->frontiers);
        self::assertSame('derived-value', $result->normalOutcomes[0]->values['return']->native());
    }
    #[DataProvider('providerDerivedSelection')]
    public function testDerivedSelectionsPreserveValues(string $body, string $expected): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function target(string $input){' . $body . '}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('xx')])])));
        self::assertSame([], $result->frontiers);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerDerivedSelection(): iterable
    {
        yield 'filter predicate selection' => ['return array_values(array_filter(["selected"],fn($value)=>$input==="xx"))[0];', 'selected'];
        yield 'replacement result' => ['return str_replace("x","y",$input);', 'yy'];
        yield 'array keys' => ['return array_keys([$input=>1])[0];', 'xx'];
        yield 'stored array keys' => ['$a=array_keys([$input=>1]);return $a[0];', 'xx'];
        yield 'lookup key' => ['return ["xx"=>"selected"][$input];', 'selected'];
        yield 'foreach key' => ['foreach([$input=>1] as $key=>$value){return $key;}return "missing";', 'xx'];
        yield 'foreach value' => ['foreach(array_keys([$input=>1]) as $value){return $value;}return "missing";', 'xx'];
        yield 'foreach reference' => ['$a=array_keys([$input=>1]);foreach($a as &$value){return $value;}return "missing";', 'xx'];
        yield 'destructuring' => ['[$value]=array_keys([$input=>1]);return $value;', 'xx'];
        yield 'reference' => ['$a=array_keys([$input=>1]);$value=&$a[0];return $value;', 'xx'];
    }
    #[DataProvider('providerCustomAggregateSelection')]
    public function testCustomAggregateSelectionsPreserveValues(string $body, mixed $expected): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('example.custom', '1', 'example.custom', 1, [0]));
        $intrinsic->method('evaluate')->willReturn(Term::fromNative(['keep' => 'derived-value', 'drop' => 'discarded']));
        $signature = new Signature([new Parameter('value', 'string')]);
        $plan = new SemanticPlan([Action::returns(new Expression(ExpressionKind::Intrinsic, 'example.custom', [Expression::parameter('value')]))]);
        $evaluator = new class (new CallDescriptor('example.custom', '1', 'derive_custom', $signature), $plan) implements CallEvaluator {
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
        $input = new Sources([new Source('fixture.php', '<?php function target(string $input){$a=derive_custom($input);' . $body . '}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions(evaluators: [$evaluator], intrinsics: [$intrinsic]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('input-value')])])));
        self::assertSame([], $result->frontiers);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return iterable<string,array{string,string|array<string,string>}>
     */
    public static function providerCustomAggregateSelection(): iterable
    {
        $array = ['keep' => 'derived-value', 'drop' => 'discarded'];
        yield 'offset' => ['return $a["keep"];', 'derived-value'];
        yield 'map identity' => ['return array_map(null,$a);', $array];
        yield 'map callback' => ['return array_map(fn($value)=>$value,$a);', $array];
        yield 'map element selection' => ['return array_map(null,$a)["keep"];', 'derived-value'];
        yield 'filter default' => ['return array_filter($a);', $array];
        yield 'filter callback' => ['return array_filter($a,fn($value)=>true);', $array];
        yield 'filter element selection' => ['return array_filter($a)["keep"];', 'derived-value'];
        yield 'reduce callback' => ['return array_reduce($a,fn($carry,$value)=>$carry??$value,null);', 'derived-value'];
        yield 'unset' => ['unset($a["drop"]);return $a;', ['keep' => 'derived-value']];
        yield 'reference' => ['$value=&$a["keep"];return $value;', 'derived-value'];
        yield 'foreach value' => ['foreach($a as $value){return $value;}return null;', 'derived-value'];
        yield 'foreach reference' => ['foreach($a as &$value){return $value;}return null;', 'derived-value'];
    }
    #[DataProvider('providerExistence')]
    public function testExistenceResultsMatchSuppliedValues(string $body): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function target(string $input){' . $body . '}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('xx')])])));
        self::assertSame([], $result->frontiers);
        self::assertFalse($result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return iterable<string,array{string}>
     */
    public static function providerExistence(): iterable
    {
        yield 'string length' => ['return isset($input[3]);'];
        yield 'invalid string key' => ['$s="abc";return isset($s[$input]);'];
        yield 'ternary' => ['return $input === "xx" ? false : true;'];
        yield 'short circuit' => ['return $input === "yy" && true;'];
        yield 'absent array key' => ['$a=["public"=>1];return isset($a[$input]);'];
    }
}
