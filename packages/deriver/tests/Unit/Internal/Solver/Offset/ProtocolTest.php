<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\Protocol
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\Protocol::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\ConditionalLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\DestructuringLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\ClassDeclaration::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Access::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Member\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Native\Signatures::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Creation::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ExceptionMatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\StateStorage::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\ProtocolAccess::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Strings::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\CompoundAssignment::class)]
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
#[UsesClass(\Deriver\Internal\Value\Comparison::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ProtocolTest extends TestCase
{
    public function testApplyReturnsAssignmentValueAfterOffsetSet(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new \Deriver\Value\Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, \Deriver\Value\Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new \Deriver\Internal\Solver\Machine($context));
        $state->registers['rhs'] = \Deriver\Value\Term::constant(7);
        $instruction = new \Deriver\Internal\IR\Instruction('write', 'write', $body->source, 'result', ['slot','rhs']);
        $paths = $protocol->apply($body, $instruction, $state, $access);
        self::assertCount(1, $paths);
        self::assertSame(7, $paths[0]->value('result')->native());
        self::assertSame(7, $paths[0]->memory->read(new \Deriver\Internal\Memory\Location('object:box', ['value']))->native());
    }
    public function testCallPreservesOriginalArrayKeysForUserMethods(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new \Deriver\Value\Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, \Deriver\Value\Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new \Deriver\Internal\Solver\Machine($context));
        $instruction = new \Deriver\Internal\IR\Instruction('get', 'read', $body->source, 'result', ['slot']);
        $paths = $protocol->call($body, $instruction, $state, $access, 'offsetGet', [new \Deriver\Internal\Solver\Call\PassedArgument(\Deriver\Value\Term::array([\Deriver\Value\Term::constant(2)]))]);
        self::assertSame([2], $paths[0]->value('result')->native());
    }
    public function testGetReturnsTheSourceMethodResult(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new \Deriver\Value\Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, \Deriver\Value\Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new \Deriver\Internal\Solver\Machine($context));
        $instruction = new \Deriver\Internal\IR\Instruction('get', 'read', $body->source, 'result', ['slot']);
        $paths = $protocol->get($body, $instruction, $state, $access);
        self::assertSame('yes', $paths[0]->value('result')->native());
    }
    public function testSilentDistinguishesExistenceFromCoalescingReads(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new \Deriver\Value\Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, \Deriver\Value\Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new \Deriver\Internal\Solver\Machine($context));
        $probe = new \Deriver\Internal\IR\Instruction('exists', 'read-silent', $body->source, 'result', ['slot'], attributes: ['existence' => true]);
        $read = new \Deriver\Internal\IR\Instruction('coalesce', 'read-silent', $body->source, 'result', ['slot']);
        self::assertTrue($protocol->silent($body, $probe, $state->fork(), $access)[0]->value('result')->native());
        self::assertSame('yes', $protocol->silent($body, $read, $state->fork(), $access)[0]->value('result')->native());
    }
    public function testIndirectCreatesTemporaryReferencesForValueReturns(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new \Deriver\Value\Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, \Deriver\Value\Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new \Deriver\Internal\Solver\Machine($context));
        $state->registers['result'] = \Deriver\Value\Term::constant(4);
        $instruction = new \Deriver\Internal\IR\Instruction('reference', 'reference', $body->source, 'result', ['slot']);
        $paths = $protocol->indirect($body, $instruction, $state, $access);
        self::assertSame('cell', $paths[0]->registers['result']->kind);
        self::assertSame(4, $paths[0]->value('result')->native());
        self::assertSame(['PHP_WARNING'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testNestedContinuesFromTheReturnedReferenceCell(): void
    {
        $context = \Tests\Fake\SolverFixture::context('<?php class Box implements ArrayAccess {public mixed $value=0;function offsetGet($key){return $key;}function offsetExists($key){return $key === "yes";}function offsetSet($key,$value){$this->value=$value;}function offsetUnset($key){$this->value=null;}}function target(){}');
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $state = new \Deriver\Internal\Solver\State();
        $state->memory->cells['object:box'] = \Deriver\Value\Term::array(['value' => \Deriver\Value\Term::constant(0)]);
        $state->memory->classes['box'] = 'Box';
        $receiver = new \Deriver\Value\Term('object', 'box', attributes: ['class' => 'Box']);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, \Deriver\Value\Term::constant('yes'));
        $protocol = new \Deriver\Internal\Solver\Offset\Protocol(new \Deriver\Internal\Solver\Machine($context));
        $location = $state->memory->allocate(\Deriver\Value\Term::array([\Deriver\Value\Term::constant(8)]));
        $state->offsets['slot'] = new \Deriver\Internal\Solver\Offset\Address('outer', \Deriver\Value\Term::constant(0));
        $nested = new \Deriver\Internal\Solver\Offset\ProtocolAccess($receiver, \Deriver\Value\Term::constant('x'), ['slot']);
        $instruction = new \Deriver\Internal\IR\Instruction('nested', 'read', $body->source, 'result', ['slot']);
        $paths = $protocol->nested($body, $instruction, $state, $nested, $location);
        self::assertSame(8, $paths[0]->value('result')->native());
    }

    /**
     * @throws JsonException If recorded engine observations cannot be decoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerArrayAccessSemantics')]
    public function testApplyPreservesRecordedArrayAccessValuesEffectsAndExceptions(string $source, string $normalJson, string $exception, bool $diagnostic): void
    {
        $expected = json_decode($normalJson, true, 512, JSON_THROW_ON_ERROR);
        $result = \Tests\Fake\Analysis::returns($source);
        self::assertSame($expected, array_map(static fn (\Deriver\Api\Result\Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame($exception === '' ? [] : [$exception], array_map(static fn (\Deriver\Api\Result\Exceptional $outcome): int|float|string|bool|null => $outcome->exception->literal, $result->exceptionalOutcomes));
        self::assertSame($diagnostic, in_array('PHP_WARNING', array_column($result->frontiers, 'code'), true));
        self::assertSame([], array_diff(array_column($result->frontiers, 'code'), ['PHP_WARNING']));
    }

    /**
     * @return iterable<string,array{string,string,string,bool}>
     */
    public static function providerArrayAccessSemantics(): iterable
    {
        foreach (\Tests\Fake\Programs\OffsetPrograms::cases() as $name => $case) {
            if (str_contains($case[0], 'ArrayAccess')) {
                yield $name => $case;
            }
        }
    }
}
