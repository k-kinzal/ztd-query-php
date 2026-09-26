<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Operation;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Operation\ScalarErrors
 */
#[CoversClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
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
#[UsesClass(\Deriver\Internal\Solver\State::class)]
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
#[UsesClass(\Deriver\Value\Term::class)]
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
        $errors = new \Deriver\Internal\Solver\Operation\ScalarErrors(\Tests\Fake\SolverFixture::context());
        self::assertFalse($errors->numeric(\Deriver\Value\Term::parameter('x', 'string')));
        self::assertTrue($errors->numeric(\Deriver\Value\Term::parameter('x', 'int|float|null')));
        self::assertTrue($errors->numeric(\Deriver\Value\Term::constant('12')));
        self::assertFalse($errors->numeric(\Deriver\Value\Term::constant('invalid')));
    }
    public function testEligibleKeepsAlreadyConcreteErrorsInOrdinaryTransfer(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['result'] = new \Deriver\Value\Term('throwable', 'TypeError');
        self::assertFalse((new \Deriver\Internal\Solver\Operation\ScalarErrors($context))->eligible(new \Deriver\Internal\IR\Instruction('op', 'binary', $source, 'result', name: '+'), $state, \Deriver\Value\Term::parameter('x'), \Deriver\Value\Term::constant(1)));
    }
    public function testPredicateUsesIntegralConversionForModulo(): void
    {
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $errors = new \Deriver\Internal\Solver\Operation\ScalarErrors(\Tests\Fake\SolverFixture::context());
        $predicate = $errors->predicate(new \Deriver\Internal\IR\Instruction('op', 'binary', $source, name: '%'), \Deriver\Value\Term::constant(0.5));
        self::assertTrue($predicate->native());
    }
    public function testMayTypeErrorRecognizesArrayUnionAndStringBitwiseOperators(): void
    {
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $errors = new \Deriver\Internal\Solver\Operation\ScalarErrors(\Tests\Fake\SolverFixture::context());
        self::assertFalse($errors->mayTypeError(new \Deriver\Internal\IR\Instruction('op', 'binary', $source, name: '+'), \Deriver\Value\Term::array([]), \Deriver\Value\Term::array([])));
        self::assertFalse($errors->mayTypeError(new \Deriver\Internal\IR\Instruction('op', 'binary', $source, name: '&'), \Deriver\Value\Term::parameter('x', 'string'), \Deriver\Value\Term::parameter('y', 'string')));
    }
    public function testUnaryKeepsThePossibleBitwiseTypeFailure(): void
    {
        $source = new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1);
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['result'] = new \Deriver\Value\Term('unary', '~', [\Deriver\Value\Term::parameter('x')]);
        $paths = (new \Deriver\Internal\Solver\Operation\ScalarErrors(\Tests\Fake\SolverFixture::context()))->unary(new \Deriver\Internal\IR\Instruction('op', 'unary', $source, 'result', name: 'Expr_BitwiseNot'), $state, \Deriver\Value\Term::parameter('x'));
        self::assertCount(2, $paths);
        self::assertSame('TypeError', $paths[1]->completion->value?->literal);
    }
}
