<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Native;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Native\Invocation;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Reference\SourceRef;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\CatchTarget::class)]
#[UsesClass(\Deriver\ControlFlow\ClassDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\PropertyDeclaration::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\Allocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\ProviderDispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Constant\ClassNames::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Evaluation\Control\Handler::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\Model\StateStorage::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
