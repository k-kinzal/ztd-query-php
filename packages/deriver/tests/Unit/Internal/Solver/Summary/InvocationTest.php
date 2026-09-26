<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Summary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Summary\Invocation
 */
#[CoversClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
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
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class InvocationTest extends TestCase
{
    public function testKeyIgnoresUnrelatedCallerCellsAndEarlierCallHistory(): void
    {
        $body = \Tests\Fake\SummaryFixture::body(\Tests\Fake\SolverFixture::context());
        $a = new \Deriver\Internal\Solver\State();
        $a->locals['x'] = $a->memory->allocate(\Deriver\Value\Term::constant(3));
        $b = new \Deriver\Internal\Solver\State();
        $b->memory->allocate(\Deriver\Value\Term::constant('unrelated'));
        $b->locals['x'] = $b->memory->allocate(\Deriver\Value\Term::constant(3));
        $invocation = new \Deriver\Internal\Solver\Summary\Invocation();
        self::assertSame($invocation->key($body, $a, ['old-a','one','two'])->id(), $invocation->key($body, $b, ['old-b','one','two'])->id());
    }
    public function testKeySeparatesBoundInputsAndConstraintPartitions(): void
    {
        $body = \Tests\Fake\SummaryFixture::body(\Tests\Fake\SolverFixture::context());
        $a = new \Deriver\Internal\Solver\State();
        $a->locals['x'] = $a->memory->allocate(\Deriver\Value\Term::constant(3));
        $b = $a->fork();
        $b->memory->write($b->locals['x'], \Deriver\Value\Term::constant(4));
        $c = $a->fork();
        $c->guard['predicate'] = true;
        $invocation = new \Deriver\Internal\Solver\Summary\Invocation();
        self::assertNotSame($invocation->key($body, $a, [])->id(), $invocation->key($body, $b, [])->id());
        self::assertNotSame($invocation->key($body, $a, [])->id(), $invocation->key($body, $c, [])->id());
    }
}
