<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Model\Effects
 */
#[CoversClass(\Deriver\Internal\Solver\Model\Effects::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class EffectsTest extends TestCase
{
    public function testApplyPreservesUnrelatedCellsAndTheOptionalExceptionalExit(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $state = new \Deriver\Internal\Solver\State();
        $state->addresses['x'] = $state->local('x');
        $state->memory->write($state->addresses['x'], \Deriver\Value\Term::constant(1));
        $state->memory->write($state->local('stable'), \Deriver\Value\Term::constant(2));
        $instruction = new \Deriver\Internal\IR\Instruction('effect', 'model-havoc', new \Deriver\Api\Reference\SourceRef('test', 'model:test', 0, 1), 'result', ['x'], 'unknown mutation');
        $paths = (new \Deriver\Internal\Solver\Model\Effects($context))->apply($instruction, $state);
        self::assertCount(2, $paths);
        self::assertSame('throw', $paths[1]->completion->kind);
        self::assertSame('opaque', $paths[0]->memory->read($state->addresses['x'])->kind);
        self::assertSame(2, $paths[0]->memory->read($state->local('stable'))->native());
    }
}
