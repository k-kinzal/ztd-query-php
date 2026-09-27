<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Native;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\Allocation;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Native\Invocation;
use Deriver\Evaluation\Call\Native\Properties;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Creation;
use Deriver\Evaluation\Call\Preparation\Methods;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\ProviderDispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionChain;
use Deriver\Evaluation\Control\ExceptionMatch;
use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\Model\StateStorage;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\CallableTransfer;
use Deriver\Evaluation\Transfer\ConstantTransfer;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ObjectAccess;
use Deriver\Evaluation\Transfer\PropertyAccessCheck;
use Deriver\Evaluation\Transfer\PropertyLookup;
use Deriver\Evaluation\Transfer\PropertyMagic;
use Deriver\Evaluation\Transfer\PropertySlot;
use Deriver\Evaluation\Transfer\PropertyTransfer;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchRequest;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Control\DestructuringLowering;
use Deriver\Source\Compilation\Control\ExceptionLowering;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\ConstantSignatures;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\NumericString;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Native\Invocation
 */
#[CoversClass(Invocation::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(ProviderDispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyMagic::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(DispatchRequest::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class InvocationTest extends TestCase
{
    /**
     * @throws JsonException If captured source metadata cannot be encoded
     */
    public function testApplyChecksInternalConstructorTypes(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){try{new Exception(previous:new stdClass);}catch(TypeError $e){return "type";}return 999;}');
        self::assertSame('type', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testCoercionsAppliesNullConversionOnlyInWeakNativeCalls(): void
    {
        $invocation = new Invocation(new Machine(\Tests\Fake\SolverFixture::context()));
        $signature = (new Signatures(\Tests\Fake\SolverFixture::context()->program))->graph('Exception', '__construct', new SourceRef('s', 'x.php', 0, 1));
        $actuals = [new PassedArgument(Term::constant(null), name:'message')];
        self::assertSame('', $invocation->coercions($signature, $actuals, false)[0]->value->literal);
        self::assertNull($invocation->coercions($signature, $actuals, true)[0]->value->literal);
    }
    /**
     * @throws JsonException If captured source metadata cannot be encoded
     */
    public function testInitializeRetainsInheritedNonzeroCodeWhenPassedZero(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class X extends Exception{protected $message="old";protected $code=9;}function target(){$e=new X(code:0);return [$e->getMessage(),$e->getCode()];}');
        self::assertSame(['',9], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testAssignedPreservesPreviousAndNormalizesLineDefault(): void
    {
        $invocation = new Invocation(new Machine(\Tests\Fake\SolverFixture::context()));
        $entry = new State();
        $entry->memory->write($entry->local('filename'), Term::constant('chosen.php'));
        self::assertNull($invocation->assigned('previous', Term::constant(null), $entry));
        self::assertSame(0, $invocation->assigned('line', Term::constant(null), $entry)?->literal);
        self::assertNull($invocation->assigned('code', Term::constant(0), $entry));
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNativeCoercions')]
    public function testCoercionsUsesParameterPositionAndNameWithoutChangingReferenceMetadata(?string $name, int $position, Term $input, Term $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $signature = (new Signatures($context->program))->graph('ErrorException', '__construct', new SourceRef('s', 'a.php', 0, 1));
        $location = new Location('argument', ['key']);
        $argument = new PassedArgument($input, $name, $location);
        $arguments = array_fill(0, $position, new PassedArgument(Term::constant(1)));
        $arguments[] = $argument;
        $result = (new Invocation(new Machine($context)))->coercions($signature, $arguments, false);
        self::assertSame($expected->kind, $result[$position]->value->kind);
        self::assertSame($expected->literal, $result[$position]->value->literal);
        self::assertSame($expected->secret, $result[$position]->value->secret);
        self::assertSame($name, $result[$position]->name);
        self::assertSame($location, $result[$position]->location);
        self::assertCount($position + 1, $result);
    }

    /**
     * @return iterable<string,array{?string,int,Term,Term}>
     */
    public static function providerNativeCoercions(): iterable
    {
        yield 'positional message' => [null,0,Term::constant(null),Term::constant('')];
        yield 'positional code' => [null,1,Term::constant(null),Term::constant(0)];
        yield 'positional severity' => [null,2,Term::constant(null),Term::constant(0)];
        yield 'nullable filename' => [null,3,Term::constant(null),Term::constant(null)];
        yield 'nullable line' => [null,4,Term::constant(null),Term::constant(null)];
        yield 'named message' => ['message',0,Term::constant(null, true),Term::constant('', true)];
        yield 'named code' => ['code',0,Term::constant(null),Term::constant(0)];
        yield 'unknown parameter' => ['missing',0,Term::constant(null),Term::constant(null)];
        yield 'extra positional' => [null,6,Term::constant(null),Term::constant(null)];
        yield 'false unchanged' => ['message',0,Term::constant(false),Term::constant(false)];
        yield 'symbolic unchanged' => ['message',0,Term::parameter('x'),Term::parameter('x')];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerAssignedFields')]
    public function testAssignedFollowsNativeFieldRetentionAndFilenameRules(string $name, Term $value, Term $filename, ?Term $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $entry = new State();
        $entry->memory->write($entry->local('filename'), $filename);
        $result = (new Invocation(new Machine($context)))->assigned($name, $value, $entry);
        self::assertEquals($expected, $result);
    }

    /**
     * @return iterable<string,array{string,Term,Term,?Term}>
     */
    public static function providerAssignedFields(): iterable
    {
        $null = Term::constant(null);
        yield 'zero code' => ['code',Term::constant(0),$null,null];
        yield 'nonzero code' => ['code',Term::constant(7),$null,Term::constant(7)];
        yield 'symbolic code' => ['code',Term::parameter('n', 'int'),$null,Term::parameter('n', 'int')];
        yield 'null previous' => ['previous',$null,$null,null];
        yield 'null filename' => ['filename',$null,$null,null];
        yield 'null line no file' => ['line',$null,$null,null];
        yield 'null line chosen file' => ['line',$null,Term::constant('chosen.php'),Term::constant(0)];
        yield 'null line unknown file' => ['line',$null,Term::parameter('file', 'string|null'),Term::opaque('RUNTIME_STACK', 'int')];
        yield 'explicit line' => ['line',Term::constant(42),$null,Term::constant(42)];
        yield 'empty message' => ['message',Term::constant(''),$null,Term::constant('')];
    }

    public function testApplyDeclinesUnknownNativeClassesAndMethods(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $invocation = new Invocation(new Machine($context));
        $instruction = new Instruction('i', 'method', new SourceRef('s', 'a.php', 0, 1), 'result');
        $receiver = new Term('object', 'e', attributes:['class' => 'Exception']);
        self::assertNull($invocation->apply('UnknownClass', 'run', [], new State(), $instruction, null, false));
        self::assertNull($invocation->apply('Exception', 'missing', [], new State(), $instruction, $receiver, false));
        self::assertSame([], $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidNativeReceivers')]
    public function testApplyRejectsCloningAndIncompatibleReceivers(string $method, ?Term $receiver): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('i', 'method', new SourceRef('s', 'a.php', 0, 1), 'result');
        $paths = (new Invocation(new Machine($context)))->apply('Exception', $method, [], new State(), $instruction, $receiver, false);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }

    /**
     * @return iterable<string,array{string,?Term}>
     */
    public static function providerInvalidNativeReceivers(): iterable
    {
        yield 'clone' => ['__clone',new Term('object', 'e', attributes:['class' => 'Exception'])];
        yield 'missing receiver' => ['getMessage',null];
        yield 'wrong class' => ['getMessage',new Term('object', 'e', attributes:['class' => 'stdClass'])];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNativeResidualMethods')]
    public function testApplyKeepsRuntimeStackAndSerializationMethodsBehindABoundary(string $method): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('i', 'method', new SourceRef('s', 'a.php', 0, 1), 'result');
        $receiver = new Term('object', 'e', attributes:['class' => 'Exception']);
        $paths = (new Invocation(new Machine($context)))->apply('Exception', $method, [], new State(), $instruction, $receiver, false);
        self::assertNotNull($paths);
        self::assertSame(['normal','throw'], array_map(static fn (State $path): string => $path->completion->kind, $paths));
        self::assertSame('MISSING_CALL_MODEL', array_values($context->frontiers)[0]->code);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerNativeResidualMethods(): iterable
    {
        yield 'trace' => ['getTrace'];
        yield 'trace string' => ['getTraceAsString'];
        yield 'string conversion' => ['__toString'];
        yield 'wake up' => ['__wakeup'];
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNativePrograms')]
    public function testApplyPreservesOracleCheckedNativeConstructorsAndGetters(string $source, string $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags:JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerNativePrograms(): iterable
    {
        return [
            'native:messageCode' => ['<?php function target(){$e=new Exception("hello",7);return [$e->getMessage(),$e->getCode(),$e->getPrevious()];}', '["hello",7,null]'],
            'native:named' => ['<?php function target(){$e=new RuntimeException(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}', '["hello",7]'],
            'native:inherited' => ['<?php class X extends Exception{}function target(){$e=new X(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}', '["hello",7]'],
            'native:codeZero' => ['<?php class X extends Exception{protected $message="x";protected $code=9;}function target(){$a=new X;$b=new X(code:0);$c=new X(message:"a");return [$a->getMessage(),$a->getCode(),$b->getMessage(),$b->getCode(),$c->getMessage(),$c->getCode()];}', '["x",9,"",9,"a",9]'],
            'native:previous' => ['<?php function target(){$p=new Exception("first");$e=new Exception(previous:$p);$previous=$e->getPrevious();$equal=$previous===$p;return [$equal,$previous->getMessage()];}', '[true,"first"]'],
            'native:parent' => ['<?php class X extends Exception{function __construct(){parent::__construct("a",2);}}function target(){$e=new X;return [$e->getMessage(),$e->getCode()];}', '["a",2]'],
            'native:clone' => ['<?php function target(){try{$x=clone new Exception;}catch(Error $e){return "error";}return 999;}', '"error"'],
            'native:arrayMessage' => ['<?php function target(){try{new Exception([]);}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:badCode' => ['<?php function target(){try{new Exception(code:"no");}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:badPrevious' => ['<?php function target(){try{new Exception(previous:new stdClass);}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:unknownNamed' => ['<?php function target(){try{new Exception(other:1);}catch(Error $e){return "name";}return 999;}', '"name"'],
            'native:extra' => ['<?php function target(){try{new Exception("",0,null,1);}catch(ArgumentCountError $e){return "count";}return 999;}', '"count"'],
            'native:strict' => ['<?php declare(strict_types=1);function target(){try{new Exception(1);}catch(TypeError $e){return "type";}return 999;}', '"type"'],
            'native:weak' => ['<?php function target(){$e=new Exception(1,"2");return [$e->getMessage(),$e->getCode()];}', '["1",2]'],
            'native:errorDefaults' => ['<?php class X extends ErrorException{protected $message="x";protected $code=9;protected int $severity=2;}function target(){$x=new X;return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}', '["x",9,1]'],
            'native:repeatPrevious' => ['<?php class X extends Exception{function clear(){parent::__construct();}function reset(){parent::__construct(previous:null);}}function target(){$p=new Exception;$x=new X("x",9,$p);$x->clear();$previous=$x->getPrevious();$a=[$x->getMessage(),$x->getCode(),$previous===$p];$x->reset();$previous=$x->getPrevious();return [$a,$x->getMessage(),$x->getCode(),$previous===$p];}', '[["x",9,true],"",9,true]'],
            'native:severity' => ['<?php function target(){$x=new ErrorException(severity:8);return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}', '["",0,8]'],
            'native:getterArity' => ['<?php function target(){try{(new Exception)->getMessage(1);}catch(ArgumentCountError $e){return "count";}return 999;}', '"count"'],
            'native:protected' => ['<?php function target(){try{return (new Exception("a"))->message;}catch(Error $e){return "access";}}', '"access"'],
            'native:sourceProperty' => ['<?php class X extends Exception{function change(){$this->message=42;$this->code="custom";}}function target(){$e=new X;$e->change();return [$e->getMessage(),$e->getCode()];}', '["42","custom"]'],
            'native:caseInsensitive' => ['<?php function target(){try{throw new runtimeexception;}catch(EXCEPTION $e){return "caught";}}', '"caught"'],
            'native:errorHierarchy' => ['<?php function target(){$e=new DivisionByZeroError("zero");return [$e instanceof ArithmeticError,$e instanceof Throwable,$e->getMessage()];}', '[true,true,"zero"]'],
            'native:missingMethod' => ['<?php function target(){try{(new Exception)->absent();}catch(Error $e){return "missing";}return 999;}', '"missing"'],
            'native:filename' => ['<?php function target(){$e=new ErrorException(filename:"chosen.php");return [$e->getFile(),$e->getLine()];}', '["chosen.php",0]'],
            'native:line' => ['<?php function target(){$e=new ErrorException(filename:"chosen.php",line:42);return [$e->getFile(),$e->getLine()];}', '["chosen.php",42]'],
        ];
    }
}
