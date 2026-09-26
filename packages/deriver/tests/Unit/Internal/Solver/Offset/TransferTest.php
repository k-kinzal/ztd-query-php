<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\Transfer
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ExceptionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
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
#[UsesClass(\Deriver\Internal\IR\CatchTarget::class)]
#[UsesClass(\Deriver\Internal\IR\ExceptionRegion::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Properties::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
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
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\StringAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Strings::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CompoundAssignment::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
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
final class TransferTest extends TestCase
{
    public function testApplyCreatesAReferenceCellForAnAbsentArrayElement(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new \Deriver\Internal\Solver\State();
        $base = $state->memory->allocate(\Deriver\Value\Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['slot'] = new \Deriver\Internal\Solver\Offset\Address('base', \Deriver\Value\Term::constant('x'));
        $path = new \Deriver\Internal\Solver\Offset\Path($context);
        $read = new \Deriver\Internal\IR\Instruction('reference', 'reference', $body->source, 'result', ['slot']);
        $paths = (new \Deriver\Internal\Solver\Offset\Transfer(new \Deriver\Internal\Solver\Machine($context)))->apply($body, $read, $state);
        self::assertCount(1, $paths);
        self::assertSame('cell', $paths[0]->registers['result']->kind);
        self::assertSame(['x' => null], $paths[0]->memory->materialize($paths[0]->memory->read($base))->native());
    }
    public function testStringRejectsReferencesWithoutChangingTheContainingString(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new \Deriver\Internal\Solver\State();
        $location = $state->memory->allocate(\Deriver\Value\Term::constant('abc'));
        $access = new \Deriver\Internal\Solver\Offset\StringAccess($location, \Deriver\Value\Term::constant(1));
        $reference = new \Deriver\Internal\IR\Instruction('reference', 'reference', $body->source, 'result', ['slot']);
        $paths = (new \Deriver\Internal\Solver\Offset\Transfer(new \Deriver\Internal\Solver\Machine($context)))->string($reference, $state, $access);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame('abc', $paths[0]->memory->read($location)->native());
    }
    public function testResultMarksConcreteErrorsAsExceptional(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $paths = (new \Deriver\Internal\Solver\Offset\Transfer(new \Deriver\Internal\Solver\Machine($context)))->result(new \Deriver\Internal\Solver\State(), $instruction, new \Deriver\Value\Term('throwable', 'TypeError'));
        self::assertSame('throw', $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
    }
    public function testBoundaryPreservesBothExitKindsAndInvalidatesAffectedState(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new \Deriver\Internal\IR\Instruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new \Deriver\Internal\Solver\State();
        $location = $state->local('value');
        $state->memory->write($location, \Deriver\Value\Term::constant(1));
        $paths = (new \Deriver\Internal\Solver\Offset\Transfer(new \Deriver\Internal\Solver\Machine($context)))->boundary($state, $instruction);
        self::assertCount(2, $paths);
        self::assertSame('normal', $paths[0]->completion->kind);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('opaque', $paths[0]->memory->read($location)->kind);
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column(array_values($context->frontiers), 'code'));
    }
    /**
     * @throws JsonException If the fixture observation cannot be encoded
     */
    public function testCompoundRejectsStringOffsetsAfterCheckingTheOriginalKey(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$a="123";$b=0;try{$a[0]+=++$b;}catch(Error $e){return [$a,$b];}return 999;}');
        self::assertSame(['123',1], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
}
