<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Operation\Conversions
 */
#[CoversClass(Conversions::class)]
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
#[UsesClass(\Deriver\ControlFlow\ClassConstant::class)]
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
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Access::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Constants::class)]
#[UsesClass(\Deriver\Evaluation\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Evaluation\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(Completion::class)]
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
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyReference::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertySlot::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PropertyTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
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
#[UsesClass(\Deriver\Reference\SourceRef::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Increment::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ConversionsTest extends TestCase
{
    public function testApplyLeavesNonconversionInstructionsToOrdinaryTransfer(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $operation = new Conversions(new Machine($context));
        self::assertNull($operation->apply($body, new Instruction('x', 'constant', $body->source), new State()));
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
        $operation = new Conversions(new Machine(\Tests\Fake\SolverFixture::context()));
        self::assertFalse($operation->object(Term::parameter('x', 'int|string')));
        self::assertTrue($operation->object(Term::parameter('x', 'mixed')));
    }
    public function testBoundaryIncludesBothCompletionKindsAndEffects(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $paths = (new Conversions(new Machine($context)))->boundary($body, new Instruction('x', 'cast', $body->source, 'result'), $state, 'object-cast');
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
    public function testStringPreservesOracleCheckedSourceEffectsAndCompletion(string $source, Term $expected): void
    {
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @return iterable<string,array{string,Term}>
     */
    public static function providerSourceStringConversions(): iterable
    {
        return array_intersect_key(\Tests\Fake\Programs\ConversionPrograms::cases(), array_fill_keys([
            'automatic Stringable interface','source string method effects','concatenation invokes both operands','source string method throws after writing','implicit string return declaration','strict implicit string return','invalid implicit string return','object without string method','closure string cast','enum string cast',
        ], true));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalarBounds')]
    public function testObjectRecognizesEveryScalarTypeAndConservativeObjectBound(Term $value, bool $expected): void
    {
        $operation = new Conversions(new Machine(\Tests\Fake\SolverFixture::context()));
        self::assertSame($expected, $operation->object($value));
    }

    /**
     * @return iterable<string,array{Term,bool}>
     */
    public static function providerScalarBounds(): iterable
    {
        yield 'constant' => [Term::constant(1),false];
        yield 'array' => [Term::array([]),false];
        yield 'uninitialized' => [new Term('uninitialized'),false];
        yield 'integer' => [Term::parameter('x', 'int'),false];
        yield 'float' => [Term::parameter('x', 'float'),false];
        yield 'string' => [Term::parameter('x', 'string'),false];
        yield 'boolean' => [Term::parameter('x', 'bool'),false];
        yield 'true' => [Term::parameter('x', 'true'),false];
        yield 'false' => [Term::parameter('x', 'false'),false];
        yield 'null' => [Term::parameter('x', 'null'),false];
        yield 'array bound' => [Term::parameter('x', 'array'),false];
        yield 'scalar union' => [Term::parameter('x', 'int|float|string|bool|true|false|null|array'),false];
        yield 'unknown' => [new Term('external', 'x'),true];
        yield 'mixed union' => [Term::parameter('x', 'string|Box'),true];
        yield 'mixed' => [Term::parameter('x'),true];
        yield 'object' => [new Term('object', 'one', attributes:['class' => 'Box']),true];
        yield 'closure' => [new Term('closure', 'closure:a.php:1'),true];
        yield 'enum' => [new Term('enum', 'E::A'),true];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOrdinaryOperations')]
    public function testApplyDefersScalarAndStrictComparisonOperations(string $operation, string $name, Term $left, Term $right): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers = ['left' => $left,'right' => $right];
        $instruction = new Instruction('i', $operation, $body->source, 'result', ['left','right'], $name);
        $result = (new Conversions(new Machine($context)))->apply($body, $instruction, $state);
        self::assertNull($result);
        self::assertSame('normal', $state->completion->kind);
        self::assertArrayNotHasKey('result', $state->registers);
        self::assertSame([], $context->frontiers);
    }

    /**
     * @return iterable<string,array{string,string,Term,Term}>
     */
    public static function providerOrdinaryOperations(): iterable
    {
        yield 'scalar int cast' => ['cast','int',Term::constant('12'),Term::constant(null)];
        yield 'object bool cast' => ['cast','bool',new Term('object', 'one'),Term::constant(null)];
        yield 'scalar equal' => ['binary','==',Term::constant(1),Term::constant(2)];
        yield 'strict identity' => ['binary','===',new Term('object', 'one'),new Term('object', 'two')];
        yield 'strict nonidentity' => ['binary','!==',new Term('object', 'one'),new Term('object', 'two')];
        yield 'arithmetic' => ['binary','+',Term::constant(1),Term::constant(2)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOpaqueConversions')]
    public function testApplyKeepsUnsupportedObjectProtocolsExplicit(string $operation, string $name, Term $left, Term $right, string $frontier): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers = ['left' => $left,'right' => $right];
        $instruction = new Instruction('i', $operation, $body->source, 'result', ['left','right'], $name);
        $paths = (new Conversions(new Machine($context)))->apply($body, $instruction, $state);
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
     * @return iterable<string,array{string,string,Term,Term,string}>
     */
    public static function providerOpaqueConversions(): iterable
    {
        $object = new Term('object', 'one', attributes:['class' => 'Box']);
        $scalar = Term::constant(1);
        yield 'object integer' => ['cast','int',$object,$scalar,'object-cast'];
        yield 'object float' => ['cast','float',$object,$scalar,'object-cast'];
        yield 'object array' => ['cast','array',$object,$scalar,'object-cast'];
        yield 'symbolic string' => ['cast','string',Term::parameter('x'),$scalar,'dynamic-string-conversion'];
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
        $instruction = new Instruction('i', 'cast', $body->source, 'result');
        $paths = (new Conversions(new Machine($context)))->string($body, $instruction, new State(), Term::array([Term::constant('private', true)]));
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
        $state = new State();
        $state->completion = new Completion('throw', new Term('throwable', 'RuntimeException'));
        $paths = (new Conversions(new Machine($context)))->returned([$state], new Instruction('i', 'cast', $body->source, 'result'), 'target');
        self::assertSame([$state], $paths);
        self::assertSame('RuntimeException', $paths[0]->completion->value?->literal);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }

    public function testReturnedPartitionsAnUnknownReturnIntoValidStringsAndTypeErrors(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new State();
        $state->registers['result'] = Term::parameter('result');
        $paths = (new Conversions(new Machine($context)))->returned([$state], new Instruction('i', 'cast', $body->source, 'result'), 'target');
        self::assertCount(2, $paths);
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
        self::assertSame('normal', $paths[1]->completion->kind);
        self::assertSame('string', $paths[1]->value('result')->attributes['type']);
    }
}
