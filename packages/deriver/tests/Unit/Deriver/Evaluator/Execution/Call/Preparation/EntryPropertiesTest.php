<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\EntryProperties;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\InvalidInputException;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(EntryProperties::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(State::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Memory::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[Small]
final class EntryPropertiesTest extends TestCase
{
    public function testApplyWithoutPropertiesLeavesTheEntryStateUntouched(): void
    {
        $symbol = 'free';
        $source = '<?php declare(strict_types=1); class Base{private string $table="users";protected ?int $limit=null;}' . 'final class Box extends Base{private string $order="name";public static int $count=0;public int|string $key=0;public $loose;function run(){}static function make(){}}function free(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
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
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context1 = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body1 = $context1->program->callable($symbol);
        self::assertNotNull($body1);
        [$context, $body] = [$context1, $body1];
        $state = new State();
        (new EntryProperties($context))->apply($body, null, [], $state);
        self::assertSame([], $state->memory->cells);
        self::assertSame([], $state->memory->propertyTypes);
    }
    public function testApplyWritesDeclaredSlotsAndKeepsTheRecordOpenForUnsuppliedProperties(): void
    {
        $symbol = 'Box::run';
        $source = '<?php declare(strict_types=1); class Base{private string $table="users";protected ?int $limit=null;}' . 'final class Box extends Base{private string $order="name";public static int $count=0;public int|string $key=0;public $loose;function run(){}static function make(){}}function free(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
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
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context1 = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body1 = $context1->program->callable($symbol);
        self::assertNotNull($body1);
        [$context, $body] = [$context1, $body1];
        $state = new State();
        $order = Term::constant('email');
        (new EntryProperties($context))->apply($body, Term::parameter('this', 'Box'), ['order' => $order, 'table' => Term::constant('posts'), 'limit' => Term::constant(null), 'loose' => Term::array([])], $state);
        $record = $state->memory->cells['object:this'];
        self::assertSame(['Box::order', 'Base::table', 'limit', 'loose'], array_keys($record->operands));
        self::assertSame($order, $record->operands['Box::order']);
        self::assertSame('posts', $record->operands['Base::table']->native());
        self::assertTrue($record->attributes['open']);
        self::assertSame(['Box::order' => 'string', 'Base::table' => 'string', 'limit' => 'int|null', 'loose' => 'mixed'], $state->memory->propertyTypes['object:this']);
    }
    public function testApplyResolvesTheClassOfAnExplicitReceiverIdentity(): void
    {
        $symbol = 'Box::run';
        $source = '<?php declare(strict_types=1); class Base{private string $table="users";protected ?int $limit=null;}' . 'final class Box extends Base{private string $order="name";public static int $count=0;public int|string $key=0;public $loose;function run(){}static function make(){}}function free(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
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
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context1 = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body1 = $context1->program->callable($symbol);
        self::assertNotNull($body1);
        [$context, $body] = [$context1, $body1];
        $state = new State();
        (new EntryProperties($context))->apply($body, new Term(TermKind::Object, 'provided', attributes: ['class' => '\Box']), ['key' => Term::constant('k')], $state);
        self::assertSame('k', $state->memory->cells['object:provided']->operands['key']->native());
    }
    public function testApplyKeepsASymbolicValueWithinTheDeclaredType(): void
    {
        $symbol = 'Box::run';
        $source = '<?php declare(strict_types=1); class Base{private string $table="users";protected ?int $limit=null;}' . 'final class Box extends Base{private string $order="name";public static int $count=0;public int|string $key=0;public $loose;function run(){}static function make(){}}function free(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
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
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context1 = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body1 = $context1->program->callable($symbol);
        self::assertNotNull($body1);
        [$context, $body] = [$context1, $body1];
        $state = new State();
        (new EntryProperties($context))->apply($body, Term::parameter('this', 'Box'), ['order' => Term::parameter('order')], $state);
        $value = $state->memory->cells['object:this']->operands['Box::order'];
        self::assertFalse($value->isConcrete());
        self::assertSame('string', $value->attributes['type'] ?? null);
    }
    /**
     * @param string $symbol Entry callable
     * @param Term|null $receiver Bound receiver
     * @param array<string, Term> $properties Supplied values
     */
    #[DataProvider('providerRejected')]
    public function testApplyRejectsPropertiesNoPhpObjectCanHave(string $symbol, ?Term $receiver, array $properties, string $message): void
    {
        $source = '<?php declare(strict_types=1); class Base{private string $table="users";protected ?int $limit=null;}' . 'final class Box extends Base{private string $order="name";public static int $count=0;public int|string $key=0;public $loose;function run(){}static function make(){}}function free(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
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
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context1 = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body1 = $context1->program->callable($symbol);
        self::assertNotNull($body1);
        [$context, $body] = [$context1, $body1];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new EntryProperties($context))->apply($body, $receiver, $properties, new State());
    }
    /**
     * @return iterable<string, array{string, Term|null, array<string, Term>, string}>
     */
    public static function providerRejected(): iterable
    {
        $value = ['order' => Term::constant('x')];
        yield 'function entry' => ['free', null, $value, 'Entry properties require an instance receiver: free'];
        yield 'static entry' => ['Box::make', null, $value, 'Entry properties require an instance receiver: Box::make'];
        yield 'anonymous receiver identity' => ['Box::run', new Term(TermKind::Object, attributes: ['class' => 'Box']), $value, 'Entry properties require an instance receiver'];
        yield 'undeclared property' => ['Box::run', Term::parameter('this', 'Box'), ['missing' => Term::constant('x')], 'Entry property is not a declared instance property: Box::$missing'];
        yield 'static property' => ['Box::run', Term::parameter('this', 'Box'), ['count' => Term::constant(1)], 'Entry property is not a declared instance property: Box::$count'];
        yield 'scalar type violation' => ['Box::run', Term::parameter('this', 'Box'), ['order' => Term::constant(1)], 'Entry property value does not satisfy the declared type string: Box::$order'];
        yield 'nullable type violation' => ['Box::run', Term::parameter('this', 'Box'), ['limit' => Term::constant('ten')], 'Entry property value does not satisfy the declared type int|null: Box::$limit'];
        yield 'union type violation' => ['Box::run', Term::parameter('this', 'Box'), ['key' => Term::constant(1.5)], 'Entry property value does not satisfy the declared type int|string: Box::$key'];
    }
}
