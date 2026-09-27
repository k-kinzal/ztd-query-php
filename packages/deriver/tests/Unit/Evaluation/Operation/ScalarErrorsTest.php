<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operation;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ExceptionMatch;
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
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
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
use Deriver\Result\Exceptional;
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
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\EffectInspection;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
use Deriver\Value\Comparison;
use Deriver\Value\Identity;
use Deriver\Value\IntegerConversion;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Evaluation\Operation\ScalarErrors
 */
#[CoversClass(ScalarErrors::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionMatch::class)]
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
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
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
#[UsesClass(Exceptional::class)]
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
#[UsesClass(CallableCompiler::class)]
#[UsesClass(EffectInspection::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(IntegerConversion::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ScalarErrorsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testPathsRetainsDivisionByZeroForASymbolicInteger(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(int $n){return 5/$n;}');
        self::assertCount(1, $result->normalOutcomes);
        self::assertCount(1, $result->exceptionalOutcomes);
        self::assertSame('DivisionByZeroError', $result->exceptionalOutcomes[0]->exception->literal);
        self::assertNotSame($result->normalOutcomes[0]->guard, $result->exceptionalOutcomes[0]->guard);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSplitHonorsAnExistingNonzeroGuard(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(int $n){if($n===0){return 0;}return 5/$n;}');
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testNumericRejectsUnconstrainedStringsAndAcceptsScalarNumbers(): void
    {
        $errors = new ScalarErrors(SolverFixture::context());
        self::assertFalse($errors->numeric(Term::parameter('x', 'string')));
        self::assertTrue($errors->numeric(Term::parameter('x', 'int|float|null')));
        self::assertTrue($errors->numeric(Term::constant('12')));
        self::assertFalse($errors->numeric(Term::constant('invalid')));
    }
    public function testEligibleKeepsAlreadyConcreteErrorsInOrdinaryTransfer(): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        $state->registers['result'] = new Term('throwable', 'TypeError');
        self::assertFalse((new ScalarErrors($context))->eligible(new Instruction('op', 'binary', $source, 'result', name: '+'), $state, Term::parameter('x'), Term::constant(1)));
    }
    public function testPredicateUsesIntegralConversionForModulo(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $errors = new ScalarErrors(SolverFixture::context());
        $predicate = $errors->predicate(new Instruction('op', 'binary', $source, name: '%'), Term::constant(0.5));
        self::assertTrue($predicate->native());
    }
    public function testMayTypeErrorRecognizesArrayUnionAndStringBitwiseOperators(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $errors = new ScalarErrors(SolverFixture::context());
        self::assertFalse($errors->mayTypeError(new Instruction('op', 'binary', $source, name: '+'), Term::array([]), Term::array([])));
        self::assertFalse($errors->mayTypeError(new Instruction('op', 'binary', $source, name: '&'), Term::parameter('x', 'string'), Term::parameter('y', 'string')));
    }
    public function testUnaryKeepsThePossibleBitwiseTypeFailure(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $state = new State();
        $state->registers['result'] = new Term('unary', '~', [Term::parameter('x')]);
        $paths = (new ScalarErrors(SolverFixture::context()))->unary(new Instruction('op', 'unary', $source, 'result', name: 'Expr_BitwiseNot'), $state, Term::parameter('x'));
        self::assertCount(2, $paths);
        self::assertSame('TypeError', $paths[1]->completion->value?->literal);
    }

    #[DataProvider('providerNumericInputs')]
    public function testNumericAcceptsOnlyGuaranteedScalarNumericConversions(Term $value, bool $expected): void
    {
        self::assertSame($expected, (new ScalarErrors(SolverFixture::context()))->numeric($value));
    }

    /**
     * @return iterable<string,array{Term,bool}>
     */
    public static function providerNumericInputs(): iterable
    {
        foreach ([null,false,true,0,1.5,' 1e2 ','+3','0'] as $i => $literal) {
            yield 'numeric literal '.$i => [Term::constant($literal),true];
        }
        foreach (['','12tail','NAN','INF'] as $literal) {
            yield 'nonnumeric '.$literal => [Term::constant($literal),false];
        }
        foreach (['int','float','bool','true','false','null','int|float|bool|null'] as $type) {
            yield 'safe type '.$type => [Term::parameter('input', $type),true];
        }
        foreach (['mixed','string','int|string','object','array'] as $type) {
            yield 'possibly invalid type '.$type => [Term::parameter('input', $type),false];
        }
    }

    #[DataProvider('providerErrorEligibility')]
    public function testEligibleRestrictsSplittingToUnresolvedNumericOperations(string $operation, string $operator, Term $left, Term $right, string $resultKind, bool $expected): void
    {
        $source = new SourceRef('snapshot', 'numeric.php', 2, 9);
        $state = new State();
        $state->registers['result'] = new Term($resultKind);
        self::assertSame($expected, (new ScalarErrors(SolverFixture::context()))->eligible(new Instruction('op', $operation, $source, 'result', name:$operator), $state, $left, $right));
    }

    /**
     * @return iterable<string,array{string,string,Term,Term,string,bool}>
     */
    public static function providerErrorEligibility(): iterable
    {
        foreach (['+','-','*','/','%','**','<<','>>','&','|','^'] as $operator) {
            yield $operator => ['binary',$operator,Term::parameter('left', 'int'),Term::constant(3),'binary',true];
        }
        yield 'other operation' => ['cast','+',Term::parameter('left'),Term::constant(3),'cast',false];
        yield 'other binary' => ['binary','.',Term::parameter('left'),Term::constant(3),'binary',false];
        yield 'concrete pair' => ['binary','+',Term::constant(1),Term::constant(3),'constant',false];
        yield 'symbolic right' => ['binary','+',Term::constant(1),Term::parameter('right'),'binary',true];
        yield 'existing failure' => ['binary','+',Term::parameter('left'),Term::constant(3),'throwable',false];
    }

    #[DataProvider('providerFailurePredicates')]
    public function testPredicateUsesDivisionOrIntegralFailureRules(string $operator, Term $right, string $comparison, string $operandKind): void
    {
        $source = new SourceRef('snapshot', 'numeric.php', 2, 9);
        $predicate = (new ScalarErrors(SolverFixture::context()))->predicate(new Instruction('op', 'binary', $source, name:$operator), $right);
        self::assertSame('binary', $predicate->kind);
        self::assertSame($comparison, $predicate->literal);
        self::assertSame($operandKind, $predicate->operands[0]->kind);
        self::assertSame(0, $predicate->operands[1]->literal);
        self::assertSame($right, $operandKind === 'cast' ? $predicate->operands[0]->operands[0] : $predicate->operands[0]);
    }

    /**
     * @return iterable<string,array{string,Term,string,string}>
     */
    public static function providerFailurePredicates(): iterable
    {
        yield 'integer division' => ['/',Term::parameter('x', 'int'),'===','parameter'];
        yield 'float division' => ['/',Term::parameter('x', 'float'),'==','parameter'];
        yield 'integer remainder' => ['%',Term::parameter('x', 'int'),'===','parameter'];
        yield 'float remainder' => ['%',Term::parameter('x', 'float'),'===','cast'];
        yield 'integer left shift' => ['<<',Term::parameter('x', 'int'),'<','parameter'];
        yield 'float left shift' => ['<<',Term::parameter('x', 'float'),'<','cast'];
        yield 'integer right shift' => ['>>',Term::parameter('x', 'int'),'<','parameter'];
        yield 'float right shift' => ['>>',Term::parameter('x', 'float'),'<','cast'];
    }

    #[DataProvider('providerUnaryPaths')]
    public function testUnaryPreservesNormalExpressionsAndOnlyAddsPossibleTypeFailures(string $operator, Term $input, string $resultKind, int $count): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('snapshot', 'numeric.php', 2, 9);
        $state = new State();
        $result = new Term($resultKind, $operator, [$input]);
        $state->registers['result'] = $result;
        $paths = (new ScalarErrors($context))->unary(new Instruction('op', 'unary', $source, 'result', name:$operator), $state, $input);
        self::assertCount($count, $paths);
        self::assertSame($state, $paths[0]);
        self::assertSame($result, $paths[0]->registers['result']);
        self::assertCount($count - 1, $context->frontiers);
        self::assertSame($count === 2 ? 'TypeError' : null, ($paths[1] ?? null)?->completion->value?->literal);
        self::assertSame('normal', $state->completion->kind);
    }

    /**
     * @return iterable<string,array{string,Term,string,int}>
     */
    public static function providerUnaryPaths(): iterable
    {
        yield 'concrete already evaluated' => ['Expr_UnaryPlus',Term::constant(1),'constant',1];
        yield 'failure already evaluated' => ['Expr_UnaryMinus',Term::parameter('x'),'throwable',1];
        yield 'integer plus' => ['Expr_UnaryPlus',Term::parameter('x', 'int'),'unary',1];
        yield 'numeric minus' => ['Expr_UnaryMinus',Term::parameter('x', 'int|float'),'unary',1];
        yield 'string plus' => ['Expr_UnaryPlus',Term::parameter('x', 'string'),'unary',2];
        yield 'mixed minus' => ['Expr_UnaryMinus',Term::parameter('x'),'unary',2];
        yield 'bitwise string integer' => ['Expr_BitwiseNot',Term::parameter('x', 'string|int'),'unary',1];
        yield 'bitwise float' => ['Expr_BitwiseNot',Term::parameter('x', 'float'),'unary',2];
        yield 'bitwise array' => ['Expr_BitwiseNot',Term::array([]),'unary',2];
        yield 'logical not' => ['Expr_BooleanNot',Term::parameter('x'),'unary',1];
    }

    public function testPathsRetainsNumericConversionFailureDependenciesAndProvenance(): void
    {
        $context = SolverFixture::context();
        $source = new SourceRef('snapshot', 'numeric.php', 2, 9);
        $state = new State();
        $left = Term::constant(7);
        $right = Term::parameter('divisor', 'string');
        $state->registers['left'] = $left;
        $state->registers['right'] = $right;
        $state->registers['result'] = new Term('binary', '/', [$left,$right]);
        $paths = (new ScalarErrors($context))->paths(new Instruction('op', 'binary', $source, 'result', ['left','right'], '/'), $state);
        self::assertCount(3, $paths);
        self::assertSame(['normal','throw','throw'], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertSame('DivisionByZeroError', $paths[1]->completion->value?->literal);
        self::assertSame('TypeError', $paths[2]->completion->value?->literal);
        self::assertNotSame($paths[0]->guard, $paths[1]->guard);
        self::assertCount(1, $context->frontiers);
        $frontier = array_values($context->frontiers)[0];
        self::assertSame('WIDENED', $frontier->code);
        self::assertSame('symbolic-numeric-conversion', $frontier->operation);
        self::assertSame($source, $frontier->at);
        self::assertNotNull($frontier->residual);
        self::assertSame([$left,$right], $frontier->residual->operands);
    }

    #[DataProvider('providerConcreteFailureGuards')]
    public function testSplitKeepsOnlyFeasiblePathsAndIndependentMemory(bool $predicate, string $completion): void
    {
        $state = new State();
        $location = $state->memory->allocate(Term::constant(7));
        $paths = (new ScalarErrors(SolverFixture::context()))->split($state, Term::constant($predicate), 'ArithmeticError');
        self::assertCount(1, $paths);
        self::assertNotSame($state, $paths[0]);
        self::assertSame($completion, $paths[0]->completion->kind);
        self::assertSame('normal', $state->completion->kind);
        $paths[0]->memory->write($location, Term::constant(9));
        self::assertSame(7, $state->memory->read($location)->literal);
    }

    /**
     * @return iterable<string,array{bool,string}>
     */
    public static function providerConcreteFailureGuards(): iterable
    {
        yield 'cannot fail' => [false,'normal'];
        yield 'must fail' => [true,'throw'];
    }
}
