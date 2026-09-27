<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Operation;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Operation\Conversions
 */
#[CoversClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
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
#[UsesClass(\Deriver\Internal\IR\ClassConstant::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ConversionsTest extends TestCase
{
    public function testApplyLeavesNonconversionInstructionsToOrdinaryTransfer(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $operation = new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine($context));
        self::assertNull($operation->apply($body, new \Deriver\Internal\IR\Instruction('x', 'constant', $body->source), new \Deriver\Internal\Solver\State()));
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testStringInvokesSourceEffectsOnce(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $n=0; function __toString():string{$this->n++;return "ok";}} function target(){$b=new B; $s=(string)$b; return [$s,$b->n];}');
        self::assertSame(['ok', 1], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testConcatKeepsLeftStringBeforeTheRightMethodMutatesItsReceiver(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{public int $n=0; function __toString():string{$this->n++;return (string)$this->n;}} function target(){$b=new B; return $b.$b;}');
        self::assertSame('12', $result->normalOutcomes[0]->values['return']->native());
    }
    public function testObjectDistinguishesProvenScalarTypesFromPossibleObjects(): void
    {
        $operation = new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        self::assertFalse($operation->object(\Deriver\Value\Term::parameter('x', 'int|string')));
        self::assertTrue($operation->object(\Deriver\Value\Term::parameter('x', 'mixed')));
    }
    public function testBoundaryIncludesBothCompletionKindsAndEffects(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->write($state->local('x'), \Deriver\Value\Term::constant(1));
        $paths = (new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine($context)))->boundary($body, new \Deriver\Internal\IR\Instruction('x', 'cast', $body->source, 'result'), $state, 'object-cast');
        self::assertSame('opaque', $paths[0]->snapshot()['x']->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReturnedEnforcesTheImplicitStringContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php class B{function __toString(){return [];}} function target(){try{return (string)new B;}catch(TypeError $e){return "caught";}}');
        self::assertSame('caught', $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSourceStringConversions')]
    public function testStringPreservesOracleCheckedSourceEffectsAndCompletion(string $source, \Deriver\Value\Term $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return iterable<string,array{string,\Deriver\Value\Term}>
     */
    public static function providerSourceStringConversions(): iterable
    {
        return array_intersect_key(\Tests\Fake\Programs\ConversionPrograms::cases(), array_fill_keys([
            'automatic Stringable interface','source string method effects','concatenation invokes both operands','source string method throws after writing','implicit string return declaration','strict implicit string return','invalid implicit string return','object without string method','closure string cast','enum string cast',
        ], true));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalarBounds')]
    public function testObjectRecognizesEveryScalarTypeAndConservativeObjectBound(\Deriver\Value\Term $value, bool $expected): void
    {
        $operation = new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine(\Tests\Fake\SolverFixture::context()));
        self::assertSame($expected, $operation->object($value));
    }

    /**
     * @return iterable<string,array{\Deriver\Value\Term,bool}>
     */
    public static function providerScalarBounds(): iterable
    {
        yield 'constant' => [\Deriver\Value\Term::constant(1),false];
        yield 'array' => [\Deriver\Value\Term::array([]),false];
        yield 'uninitialized' => [new \Deriver\Value\Term('uninitialized'),false];
        yield 'integer' => [\Deriver\Value\Term::parameter('x', 'int'),false];
        yield 'float' => [\Deriver\Value\Term::parameter('x', 'float'),false];
        yield 'string' => [\Deriver\Value\Term::parameter('x', 'string'),false];
        yield 'boolean' => [\Deriver\Value\Term::parameter('x', 'bool'),false];
        yield 'true' => [\Deriver\Value\Term::parameter('x', 'true'),false];
        yield 'false' => [\Deriver\Value\Term::parameter('x', 'false'),false];
        yield 'null' => [\Deriver\Value\Term::parameter('x', 'null'),false];
        yield 'array bound' => [\Deriver\Value\Term::parameter('x', 'array'),false];
        yield 'scalar union' => [\Deriver\Value\Term::parameter('x', 'int|float|string|bool|true|false|null|array'),false];
        yield 'unknown' => [new \Deriver\Value\Term('external', 'x'),true];
        yield 'mixed union' => [\Deriver\Value\Term::parameter('x', 'string|Box'),true];
        yield 'mixed' => [\Deriver\Value\Term::parameter('x'),true];
        yield 'object' => [new \Deriver\Value\Term('object', 'one', attributes:['class' => 'Box']),true];
        yield 'closure' => [new \Deriver\Value\Term('closure', 'closure:a.php:1'),true];
        yield 'enum' => [new \Deriver\Value\Term('enum', 'E::A'),true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOrdinaryOperations')]
    public function testApplyDefersScalarAndStrictComparisonOperations(string $operation, string $name, \Deriver\Value\Term $left, \Deriver\Value\Term $right): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers = ['left' => $left,'right' => $right];
        $instruction = new \Deriver\Internal\IR\Instruction('i', $operation, $body->source, 'result', ['left','right'], $name);
        $result = (new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine($context)))->apply($body, $instruction, $state);
        self::assertNull($result);
        self::assertSame('normal', $state->completion->kind);
        self::assertArrayNotHasKey('result', $state->registers);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{string,string,\Deriver\Value\Term,\Deriver\Value\Term}>
     */
    public static function providerOrdinaryOperations(): iterable
    {
        yield 'scalar int cast' => ['cast','int',\Deriver\Value\Term::constant('12'),\Deriver\Value\Term::constant(null)];
        yield 'object bool cast' => ['cast','bool',new \Deriver\Value\Term('object', 'one'),\Deriver\Value\Term::constant(null)];
        yield 'scalar equal' => ['binary','==',\Deriver\Value\Term::constant(1),\Deriver\Value\Term::constant(2)];
        yield 'strict identity' => ['binary','===',new \Deriver\Value\Term('object', 'one'),new \Deriver\Value\Term('object', 'two')];
        yield 'strict nonidentity' => ['binary','!==',new \Deriver\Value\Term('object', 'one'),new \Deriver\Value\Term('object', 'two')];
        yield 'arithmetic' => ['binary','+',\Deriver\Value\Term::constant(1),\Deriver\Value\Term::constant(2)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOpaqueConversions')]
    public function testApplyKeepsUnsupportedObjectProtocolsExplicit(string $operation, string $name, \Deriver\Value\Term $left, \Deriver\Value\Term $right, string $frontier): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers = ['left' => $left,'right' => $right];
        $instruction = new \Deriver\Internal\IR\Instruction('i', $operation, $body->source, 'result', ['left','right'], $name);
        $paths = (new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine($context)))->apply($body, $instruction, $state);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('opaque', $paths[0]->value('result')->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        $frontiers = array_values($context->frontiers);
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $frontiers[0]->code);
        self::assertSame($frontier, $frontiers[0]->operation);
        self::assertSame($body->source, $frontiers[0]->at);
    }

    /**
     * @return iterable<string,array{string,string,\Deriver\Value\Term,\Deriver\Value\Term,string}>
     */
    public static function providerOpaqueConversions(): iterable
    {
        $object = new \Deriver\Value\Term('object', 'one', attributes:['class' => 'Box']);
        $scalar = \Deriver\Value\Term::constant(1);
        yield 'object integer' => ['cast','int',$object,$scalar,'object-cast'];
        yield 'object float' => ['cast','float',$object,$scalar,'object-cast'];
        yield 'object array' => ['cast','array',$object,$scalar,'object-cast'];
        yield 'symbolic string' => ['cast','string',\Deriver\Value\Term::parameter('x'),$scalar,'dynamic-string-conversion'];
        yield 'object equality' => ['binary','==',$object,$scalar,'object-comparison'];
        yield 'object inequality' => ['binary','!=',$scalar,$object,'object-comparison'];
        yield 'object less' => ['binary','<',$object,$scalar,'object-comparison'];
        yield 'object less equal' => ['binary','<=',$object,$scalar,'object-comparison'];
        yield 'object greater' => ['binary','>',$object,$scalar,'object-comparison'];
        yield 'object greater equal' => ['binary','>=',$object,$scalar,'object-comparison'];
        yield 'object ordering' => ['binary','<=>',$object,$scalar,'object-comparison'];
    }

    public function testStringRecordsArrayWarningsAndPreservesConfidentiality(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'cast', $body->source, 'result');
        $paths = (new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine($context)))->string($body, $instruction, new \Deriver\Internal\Solver\State(), \Deriver\Value\Term::array([\Deriver\Value\Term::constant('private', true)]));
        self::assertCount(1, $paths);
        self::assertSame('Array', $paths[0]->value('result')->literal);
        self::assertTrue($paths[0]->value('result')->isSecret());
        $frontiers = array_values($context->frontiers);
        self::assertSame('PHP_WARNING', $frontiers[0]->code);
        self::assertSame('array-to-string', $frontiers[0]->operation);
    }

    public function testReturnedPreservesThrownCompletionsWithoutCheckingTheirReturnRegister(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->completion = new \Deriver\Internal\Solver\Completion('throw', new \Deriver\Value\Term('throwable', 'RuntimeException'));
        $paths = (new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine($context)))->returned([$state], new \Deriver\Internal\IR\Instruction('i', 'cast', $body->source, 'result'), 'target');
        self::assertSame([$state], $paths);
        self::assertSame('RuntimeException', $paths[0]->completion->value?->literal);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }

    public function testReturnedPartitionsAnUnknownReturnIntoValidStringsAndTypeErrors(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['result'] = \Deriver\Value\Term::parameter('result');
        $paths = (new \Deriver\Internal\Solver\Operation\Conversions(new \Deriver\Internal\Solver\Machine($context)))->returned([$state], new \Deriver\Internal\IR\Instruction('i', 'cast', $body->source, 'result'), 'target');
        self::assertCount(2, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
        self::assertSame('normal', $paths[1]->completion->kind);
        self::assertSame('string', $paths[1]->value('result')->attributes['type']);
    }
}
