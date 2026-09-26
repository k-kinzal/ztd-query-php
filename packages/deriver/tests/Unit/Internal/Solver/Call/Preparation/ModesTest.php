<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Preparation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Preparation\Modes
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
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
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ModesTest extends TestCase
{
    public function testPositionCountsExpandedPositionalArgumentsAndRetainsUnknownUnpack(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $state = new \Deriver\Internal\Solver\State();
        $instruction = new \Deriver\Internal\IR\Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $state->registers['array'] = \Deriver\Value\Term::fromNative([1,2]);
        $call = new \Deriver\Internal\IR\Instruction('arg', 'argument', $body->source, 'result', arguments: [new \Deriver\Internal\IR\Argument('array', unpack: true)]);
        $modes = new \Deriver\Internal\Solver\Call\Preparation\Modes($machine);
        self::assertSame(2, $modes->position($call, $state));
        $state->registers['array'] = \Deriver\Value\Term::opaque('unknown', 'array');
        self::assertNull($modes->position($call, $state));
    }
    public function testSelectResolvesNamedAndVariadicReferenceParameters(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $state = new \Deriver\Internal\Solver\State();
        $instruction = new \Deriver\Internal\IR\Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $modes = new \Deriver\Internal\Solver\Call\Preparation\Modes($machine);
        $signature = new \Deriver\Internal\IR\CallableIR('consume', [new \Deriver\Internal\IR\Parameter('value'), new \Deriver\Internal\IR\Parameter('refs', byReference: true, variadic: true)], [], $body->source);
        $target = new \Deriver\Internal\Solver\Call\Preparation\Target($signature);
        self::assertFalse($modes->select($target, 0, null));
        self::assertTrue($modes->select($target, 2, null));
        self::assertTrue($modes->select($target, 0, 'refs'));
        self::assertTrue($modes->select($target, 0, 'extra'));
        self::assertNull($modes->select($target, null, null));
        self::assertNull($modes->select(new \Deriver\Internal\Solver\Call\Preparation\Target(), 0, null));
    }
}
