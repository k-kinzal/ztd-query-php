<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Reference\SourceRef;
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
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ExceptionMatch::class)]
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
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Exceptional::class)]
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
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\EffectInspection::class)]
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
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Comparison::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
