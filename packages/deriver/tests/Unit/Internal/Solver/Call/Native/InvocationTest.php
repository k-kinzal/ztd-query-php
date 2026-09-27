<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Native;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Native\Invocation
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\ConstantSignatures::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\PropertyDeclaration::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Allocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\MethodInvocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Methods::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ProviderDispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\UnknownCall::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionChain::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Handler::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CallableTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ObjectAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyAccessCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyLookup::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyMagic::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchDecision::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchRequest::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
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
        $invocation = new \Deriver\Internal\Solver\Call\Native\Invocation(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        $signature = (new \Deriver\Internal\Solver\Call\Native\Signatures(\Tests\Fake\SolverFixture::context()->program))->graph('Exception', '__construct', new \Deriver\Api\Reference\SourceRef('s', 'x.php', 0, 1));
        $actuals = [new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(null), name:'message')];
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
        $invocation = new \Deriver\Internal\Solver\Call\Native\Invocation(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        $entry = new \Deriver\Internal\Solver\State();
        $entry->memory->write($entry->local('filename'), \Deriver\Value\Term::constant('chosen.php'));
        self::assertNull($invocation->assigned('previous', \Deriver\Value\Term::constant(null), $entry));
        self::assertSame(0, $invocation->assigned('line', \Deriver\Value\Term::constant(null), $entry)?->literal);
        self::assertNull($invocation->assigned('code', \Deriver\Value\Term::constant(0), $entry));
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNativeCoercions')]
    public function testCoercionsUsesParameterPositionAndNameWithoutChangingReferenceMetadata(?string $name, int $position, \Deriver\Value\Term $input, \Deriver\Value\Term $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $signature = (new \Deriver\Internal\Solver\Call\Native\Signatures($context->program))->graph('ErrorException', '__construct', new \Deriver\Api\Reference\SourceRef('s', 'a.php', 0, 1));
        $location = new \Deriver\Internal\Memory\Location('argument', ['key']);
        $argument = new \Deriver\Internal\Solver\Call\PassedArgument($input, $name, $location);
        $arguments = array_fill(0, $position, new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::constant(1)));
        $arguments[] = $argument;
        $result = (new \Deriver\Internal\Solver\Call\Native\Invocation(new \Deriver\Internal\Solver\Machine($context)))->coercions($signature, $arguments, false);
        self::assertSame($expected->kind, $result[$position]->value->kind);
        self::assertSame($expected->literal, $result[$position]->value->literal);
        self::assertSame($expected->secret, $result[$position]->value->secret);
        self::assertSame($name, $result[$position]->name);
        self::assertSame($location, $result[$position]->location);
        self::assertCount($position + 1, $result);
    }

    /**
     * @return iterable<string,array{?string,int,\Deriver\Value\Term,\Deriver\Value\Term}>
     */
    public static function providerNativeCoercions(): iterable
    {
        yield 'positional message' => [null,0,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant('')];
        yield 'positional code' => [null,1,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant(0)];
        yield 'positional severity' => [null,2,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant(0)];
        yield 'nullable filename' => [null,3,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant(null)];
        yield 'nullable line' => [null,4,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant(null)];
        yield 'named message' => ['message',0,\Deriver\Value\Term::constant(null, true),\Deriver\Value\Term::constant('', true)];
        yield 'named code' => ['code',0,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant(0)];
        yield 'unknown parameter' => ['missing',0,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant(null)];
        yield 'extra positional' => [null,6,\Deriver\Value\Term::constant(null),\Deriver\Value\Term::constant(null)];
        yield 'false unchanged' => ['message',0,\Deriver\Value\Term::constant(false),\Deriver\Value\Term::constant(false)];
        yield 'symbolic unchanged' => ['message',0,\Deriver\Value\Term::parameter('x'),\Deriver\Value\Term::parameter('x')];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerAssignedFields')]
    public function testAssignedFollowsNativeFieldRetentionAndFilenameRules(string $name, \Deriver\Value\Term $value, \Deriver\Value\Term $filename, ?\Deriver\Value\Term $expected): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $entry = new \Deriver\Internal\Solver\State();
        $entry->memory->write($entry->local('filename'), $filename);
        $result = (new \Deriver\Internal\Solver\Call\Native\Invocation(new \Deriver\Internal\Solver\Machine($context)))->assigned($name, $value, $entry);
        self::assertEquals($expected, $result);
    }

    /**
     * @return iterable<string,array{string,\Deriver\Value\Term,\Deriver\Value\Term,?\Deriver\Value\Term}>
     */
    public static function providerAssignedFields(): iterable
    {
        $null = \Deriver\Value\Term::constant(null);
        yield 'zero code' => ['code',\Deriver\Value\Term::constant(0),$null,null];
        yield 'nonzero code' => ['code',\Deriver\Value\Term::constant(7),$null,\Deriver\Value\Term::constant(7)];
        yield 'symbolic code' => ['code',\Deriver\Value\Term::parameter('n', 'int'),$null,\Deriver\Value\Term::parameter('n', 'int')];
        yield 'null previous' => ['previous',$null,$null,null];
        yield 'null filename' => ['filename',$null,$null,null];
        yield 'null line no file' => ['line',$null,$null,null];
        yield 'null line chosen file' => ['line',$null,\Deriver\Value\Term::constant('chosen.php'),\Deriver\Value\Term::constant(0)];
        yield 'null line unknown file' => ['line',$null,\Deriver\Value\Term::parameter('file', 'string|null'),\Deriver\Value\Term::opaque('RUNTIME_STACK', 'int')];
        yield 'explicit line' => ['line',\Deriver\Value\Term::constant(42),$null,\Deriver\Value\Term::constant(42)];
        yield 'empty message' => ['message',\Deriver\Value\Term::constant(''),$null,\Deriver\Value\Term::constant('')];
    }

    public function testApplyDeclinesUnknownNativeClassesAndMethods(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $invocation = new \Deriver\Internal\Solver\Call\Native\Invocation(new \Deriver\Internal\Solver\Machine($context));
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'method', new \Deriver\Api\Reference\SourceRef('s', 'a.php', 0, 1), 'result');
        $receiver = new \Deriver\Value\Term('object', 'e', attributes:['class' => 'Exception']);
        self::assertNull($invocation->apply('UnknownClass', 'run', [], new \Deriver\Internal\Solver\State(), $instruction, null, false));
        self::assertNull($invocation->apply('Exception', 'missing', [], new \Deriver\Internal\Solver\State(), $instruction, $receiver, false));
        self::assertSame([], $context->frontiers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidNativeReceivers')]
    public function testApplyRejectsCloningAndIncompatibleReceivers(string $method, ?\Deriver\Value\Term $receiver): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'method', new \Deriver\Api\Reference\SourceRef('s', 'a.php', 0, 1), 'result');
        $paths = (new \Deriver\Internal\Solver\Call\Native\Invocation(new \Deriver\Internal\Solver\Machine($context)))->apply('Exception', $method, [], new \Deriver\Internal\Solver\State(), $instruction, $receiver, false);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }

    /**
     * @return iterable<string,array{string,?\Deriver\Value\Term}>
     */
    public static function providerInvalidNativeReceivers(): iterable
    {
        yield 'clone' => ['__clone',new \Deriver\Value\Term('object', 'e', attributes:['class' => 'Exception'])];
        yield 'missing receiver' => ['getMessage',null];
        yield 'wrong class' => ['getMessage',new \Deriver\Value\Term('object', 'e', attributes:['class' => 'stdClass'])];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerNativeResidualMethods')]
    public function testApplyKeepsRuntimeStackAndSerializationMethodsBehindABoundary(string $method): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'method', new \Deriver\Api\Reference\SourceRef('s', 'a.php', 0, 1), 'result');
        $receiver = new \Deriver\Value\Term('object', 'e', attributes:['class' => 'Exception']);
        $paths = (new \Deriver\Internal\Solver\Call\Native\Invocation(new \Deriver\Internal\Solver\Machine($context)))->apply('Exception', $method, [], new \Deriver\Internal\Solver\State(), $instruction, $receiver, false);
        self::assertNotNull($paths);
        self::assertSame(['normal','throw'], array_map(static fn (\Deriver\Internal\Solver\State $path): string => $path->completion->kind, $paths));
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
