<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Compilation;

use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\CallableGraph;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlanCompiler::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallResolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ConstantTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(LocationRef::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanLocations::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(\Deriver\Model\ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ModelPrecedence::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\SignatureIdentity::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
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
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class PlanCompilerTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCompilePreservesTheSemanticContract(): void
    {
        $model = new \Tests\Fake\PlanModel(
            new ModelDescriptor('example.key', '1', 'key', new Signature([new \Deriver\Model\Signature\Parameter('id', 'int')])),
            new SemanticPlan([Action::returns(Expression::binary('.', Expression::literal(Term::constant('user:')), Expression::parameter('id')))]),
        );
        $session = \Tests\Fake\Analysis::session('<?php function target(){return key(3);}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('user:3', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testExpressionLowersParameterReadsThroughStorage(): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:example', 0, 1));
        $compiler->expression(Expression::parameter('input'));
        self::assertSame(['local','read'], array_column($compiler->instructions[0], 'operation'));
        self::assertSame('input', $compiler->instructions[0][0]->name);
    }
    public function testActionsStopsAfterACompletion(): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:example', 0, 1));
        $compiler->actions([Action::returns(Expression::literal(Term::constant(1))),new Action('unknown')]);
        self::assertSame('return', $compiler->terminators[0]->kind);
        self::assertCount(1, $compiler->instructions[0]);
    }
    public function testChoiceKeepsBothCorrelatedCompletions(): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:example', 0, 1));
        $action = Action::choice(Expression::parameter('flag'), [Action::returns(Expression::literal(Term::constant('yes')))], [Action::returns(Expression::literal(Term::constant('no')))]);
        $compiler->choice($action, 'predicate');
        self::assertSame([1,2], $compiler->terminators[0]->targets);
        self::assertSame('yes', $compiler->instructions[1][0]->constant?->native());
        self::assertSame('no', $compiler->instructions[2][0]->constant?->native());
    }
    public function testEmitAllocatesUniqueRegistersWithModelProvenance(): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:example', 0, 1));
        $a = $compiler->emit('constant', constant:Term::constant(1));
        $b = $compiler->emit('copy', [$a]);
        self::assertNotSame($a, $b);
        self::assertSame('model:example', $compiler->instructions[0][0]->source->path);
        self::assertSame([$a], $compiler->instructions[0][1]->operands);
    }
    public function testCompilePreservesTheLexicalClassForRelativeReturnTypes(): void
    {
        $compiler = new PlanCompiler(new SourceRef('test', 'model:sample', 0, 1));
        $body = $compiler->compile(new ModelDescriptor('sample', '1', 'Box::same', new Signature(returnType: 'self')), new SemanticPlan([Action::returns(Expression::receiver())]));
        self::assertSame('Box', $body->className);
        self::assertSame('self', $body->returnType);
    }
    public function testCompileRetainsSignatureDefaultsAndSourceMethodMetadata(): void
    {
        $at = new SourceRef('snapshot', 'model:sample', 2, 8, 3);
        $default = Term::constant(null);
        $signature = new Signature([
            new \Deriver\Model\Signature\Parameter('item', 'int', true),
            new \Deriver\Model\Signature\Parameter('fallback', 'string|null', default:$default),
            new \Deriver\Model\Signature\Parameter('rest', 'mixed', variadic:true),
        ], returnType:'self', allowExtraArguments:false, byReference:true);
        $source = new CallableGraph('Child::alias', [], [], $at, className:'ParentClass', visibility:'protected', static:true);
        $body = (new PlanCompiler($at))->compile(new ModelDescriptor('sample', '1', 'Child::alias', $signature), new SemanticPlan([]), $source);
        self::assertSame('Child::alias', $body->symbol);
        self::assertSame('ParentClass', $body->className);
        self::assertSame('protected', $body->visibility);
        self::assertTrue($body->static);
        self::assertTrue($body->byReference);
        self::assertFalse($body->allowExtraArguments);
        self::assertSame('self', $body->returnType);
        self::assertSame($at, $body->source);
        self::assertSame(['item','fallback','rest'], array_column($body->parameters, 'name'));
        self::assertSame(['int','string|null','mixed'], array_column($body->parameters, 'type'));
        self::assertSame([true,false,false], array_column($body->parameters, 'byReference'));
        self::assertSame([false,false,true], array_column($body->parameters, 'variadic'));
        self::assertNull($body->parameters[0]->default);
        self::assertNull($body->parameters[2]->default);
        $initializer = $body->parameters[1]->default;
        self::assertNotNull($initializer);
        self::assertSame('Child::alias:default:fallback', $initializer->symbol);
        self::assertSame($default, $initializer->blocks[0]->instructions[0]->constant);
        self::assertSame('constant', $initializer->blocks[0]->instructions[0]->operation);
        self::assertSame($initializer->blocks[0]->instructions[0]->result, $initializer->blocks[0]->terminator->operand);
        self::assertSame('return', $initializer->blocks[0]->terminator->kind);
        self::assertSame('return', $body->blocks[0]->terminator->kind);
    }

    public function testExpressionReadsAnAbstractSlotThroughItsReceiverAddress(): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:sample', 0, 1));
        $result = $compiler->expression(Expression::state('domain.value', Expression::parameter('owner')));
        $instructions = $compiler->instructions[0];
        self::assertSame(['local','read','model-state-address','read'], array_column($instructions, 'operation'));
        self::assertSame('owner', $instructions[0]->name);
        self::assertSame('domain.value', $instructions[2]->name);
        self::assertSame([$instructions[1]->result], $instructions[2]->operands);
        self::assertSame([$instructions[2]->result], $instructions[3]->operands);
        self::assertSame($instructions[3]->result, $result);
    }

    public function testExpressionReadsADeclaredParameterLocation(): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:sample', 0, 1));
        $result = $compiler->expression(Expression::read(LocationRef::parameter('input')));
        $instructions = $compiler->instructions[0];
        self::assertSame(['local','read'], array_column($instructions, 'operation'));
        self::assertSame('input', $instructions[0]->name);
        self::assertSame([$instructions[0]->result], $instructions[1]->operands);
        self::assertSame($instructions[1]->result, $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerPureExpressions')]
    public function testExpressionPreservesTheOpcodePayloadAndOperandOrder(string $opcode, string $name): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:sample', 0, 1));
        $left = Term::constant(10);
        $right = Term::constant(3);
        $result = $compiler->expression(new Expression($opcode, $name, [Expression::literal($left),Expression::literal($right)]));
        $instructions = $compiler->instructions[0];
        self::assertSame(['constant','constant',$opcode], array_column($instructions, 'operation'));
        self::assertSame($left, $instructions[0]->constant);
        self::assertSame($right, $instructions[1]->constant);
        self::assertSame([$instructions[0]->result,$instructions[1]->result], $instructions[2]->operands);
        self::assertSame($name, $instructions[2]->name);
        self::assertSame($result, $instructions[2]->result);
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerPureExpressions(): iterable
    {
        yield 'binary' => ['binary','-'];
        yield 'intrinsic' => ['intrinsic','domain.join'];
        yield 'array read' => ['array-read',''];
    }

    public function testExpressionRejectsUnsupportedOpcodes(): void
    {
        $compiler = new PlanCompiler(new SourceRef('s', 'model:sample', 0, 1));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('MODEL_CONTRACT_VIOLATION: unsupported expression missing');
        $compiler->expression(new Expression('missing'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testChoiceJoinsFallingThroughBranchesBeforeTheCommonReturn(): void
    {
        $chosen = Expression::parameter('@chosen');
        $plan = new SemanticPlan([
            Action::choice(Expression::parameter('flag'), [
                new Action('write-parameter', [Expression::literal(Term::constant('yes'))], '@chosen'),
            ], [
                new Action('write-parameter', [Expression::literal(Term::constant('no'))], '@chosen'),
            ]),
            Action::returns($chosen),
        ]);
        $model = new \Tests\Fake\PlanModel(new ModelDescriptor('sample', '1', 'remote', new Signature([new \Deriver\Model\Signature\Parameter('flag', 'bool')])), $plan);
        $session = \Tests\Fake\Analysis::session('<?php function target(){return [remote(true),remote(false)];}', new Configuration(models:[$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(['yes','no'], $result->normalOutcomes[0]->values['return']->native());
    }

    public function testEmitRetainsArgumentsAttributesAndPerBlockProvenance(): void
    {
        $at = new SourceRef('s', 'model:sample', 0, 1);
        $compiler = new PlanCompiler($at);
        $argument = new Argument('input', 'named', true);
        $first = $compiler->emit('call', ['callable'], 'target', arguments:[$argument], attributes:['by-reference' => true]);
        $compiler->current = 1;
        $second = $compiler->emit('copy', [$first]);
        self::assertSame('m0', $first);
        self::assertSame('m1', $second);
        self::assertSame('model:sample:m0', $compiler->instructions[0][0]->id);
        self::assertSame($at, $compiler->instructions[0][0]->source);
        self::assertSame('call', $compiler->instructions[0][0]->operation);
        self::assertSame('target', $compiler->instructions[0][0]->name);
        self::assertSame([$argument], $compiler->instructions[0][0]->arguments);
        self::assertSame(['by-reference' => true], $compiler->instructions[0][0]->attributes);
        self::assertSame('model:sample:m1', $compiler->instructions[1][0]->id);
        self::assertSame([$first], $compiler->instructions[1][0]->operands);
        self::assertCount(1, $compiler->instructions[0]);
        self::assertCount(1, $compiler->instructions[1]);
    }
}
