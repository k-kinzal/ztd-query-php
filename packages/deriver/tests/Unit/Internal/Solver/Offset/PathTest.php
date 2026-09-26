<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\Path
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\Query::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
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
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Strings::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PathTest extends TestCase
{
    public function testChainPreservesTheOriginalContainerAndNestedKeys(): void
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
        $state->offsets['nested'] = new \Deriver\Internal\Solver\Offset\Address('slot', \Deriver\Value\Term::constant('y'));
        $chain = $path->chain($state, 'nested');
        self::assertSame($base, $chain['base']);
        self::assertSame(['slot', 'nested'], $chain['offsets']);
    }
    public function testReadDoesNotAutovivifyNullContainers(): void
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
        $read = new \Deriver\Internal\IR\Instruction('read', 'read', $body->source, 'result', ['slot']);
        $result = $path->read($state, $read);
        self::assertInstanceOf(\Deriver\Value\Term::class, $result);
        self::assertNull($result->native());
        self::assertNull($state->memory->read($base)->native());
    }
    public function testLocateRetainsArrayCreationBeforeAnInvalidKey(): void
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
        $state->offsets['slot'] = new \Deriver\Internal\Solver\Offset\Address('base', \Deriver\Value\Term::array([]));
        $result = $path->locate($body, $state, $instruction);
        self::assertInstanceOf(\Deriver\Value\Term::class, $result);
        self::assertSame('TypeError', $result->literal);
        self::assertSame([], $state->memory->read($base)->native());
    }
    public function testPrepareRejectsScalarMutationWithoutChangingTheScalar(): void
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
        $state->memory->write($base, \Deriver\Value\Term::constant(4));
        self::assertSame('Error', $path->prepare($body, $state, $base, $instruction, true)->literal);
        self::assertSame(4, $state->memory->read($base)->native());
    }
    public function testCreateChecksDirectPropertyTypesBeforeArrayCreation(): void
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
        $state->memory->cells['object'] = \Deriver\Value\Term::array(['field' => \Deriver\Value\Term::constant(null)]);
        $state->memory->propertyTypes['object']['field'] = 'int|null';
        $address = new \Deriver\Internal\Memory\Location('object', ['field']);
        self::assertSame('TypeError', $path->create($body, $state, $address, \Deriver\Value\Term::constant(null), $instruction)->literal);
        self::assertNull($state->memory->read($address)->native());
    }
}
