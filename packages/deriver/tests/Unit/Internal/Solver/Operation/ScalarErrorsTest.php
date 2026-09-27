<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Operation;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Operation\ScalarErrors;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

/**
 * @covers \Deriver\Internal\Solver\Operation\ScalarErrors
 */
#[CoversClass(ScalarErrors::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Exceptional::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\EffectInspection::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
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
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
