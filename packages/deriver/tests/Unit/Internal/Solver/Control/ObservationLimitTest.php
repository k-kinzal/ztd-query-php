<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Control\ObservationLimit
 */
#[CoversClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
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
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\Lattice::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ObservationLimitTest extends TestCase
{
    public function testEnforceRetainsValuesOutsideTheFirstPartition(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget: new \Deriver\Api\Query\Budget(partitions: 2));
        $context->normal = [new \Deriver\Api\Result\Alternative(['return' => \Deriver\Value\Term::constant(1)]), new \Deriver\Api\Result\Alternative(['return' => \Deriver\Value\Term::constant(2)]), new \Deriver\Api\Result\Alternative(['return' => \Deriver\Value\Term::constant(99)])];
        (new \Deriver\Internal\Solver\Control\ObservationLimit($context))->enforce(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1));
        self::assertCount(1, $context->normal);
        self::assertTrue((new \Deriver\Internal\Value\Lattice())->contains($context->normal[0]->values['return'], \Deriver\Value\Term::constant(99)));
        self::assertCount(2, $context->frontiers);
    }
    public function testJoinPreservesEqualFieldsAndIncludesAbsentFields(): void
    {
        $join = new \Deriver\Internal\Solver\Control\ObservationLimit(\Tests\Fake\SolverFixture::context());
        $values = $join->join([['stable' => \Deriver\Value\Term::constant('x'), 'optional' => \Deriver\Value\Term::constant(2)], ['stable' => \Deriver\Value\Term::constant('x')]]);
        self::assertSame('x', $values['stable']->native());
        self::assertSame('mixed', $values['optional']->attributes['type']);
    }
    public function testBoundaryRecordsBudgetAndCorrelationSeparately(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        (new \Deriver\Internal\Solver\Control\ObservationLimit($context))->boundary(new \Deriver\Api\Reference\SourceRef('test', 'fixture.php', 0, 1));
        self::assertSame(['BUDGET_EXCEEDED', 'CORRELATION_RELAXED'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testStorageWidensDifferentHeapFieldsAndKeepsCommonAliases(): void
    {
        $a = new \Deriver\Api\Result\StorageSnapshot(['x' => new \Deriver\Value\Term('cell', 'a')], ['a' => \Deriver\Value\Term::constant(1)]);
        $b = new \Deriver\Api\Result\StorageSnapshot(['x' => new \Deriver\Value\Term('cell', 'a')], ['a' => \Deriver\Value\Term::constant(2)]);
        $joined = (new \Deriver\Internal\Solver\Control\ObservationLimit(\Tests\Fake\SolverFixture::context()))->storage([$a, $b]);
        self::assertSame('a', $joined->bindings['x']->literal);
        self::assertNotSame('constant', $joined->cells['a']->kind);
    }
}
