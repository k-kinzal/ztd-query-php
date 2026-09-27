<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Query\Budget;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\StorageSnapshot;
use Deriver\Value\Lattice;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Control\ObservationLimit
 */
#[CoversClass(ObservationLimit::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(Lattice::class)]
#[UsesClass(Term::class)]
#[Small]
final class ObservationLimitTest extends TestCase
{
    public function testEnforceRetainsValuesOutsideTheFirstPartition(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget: new Budget(partitions: 2));
        $context->normal = [new Alternative(['return' => Term::constant(1)]), new Alternative(['return' => Term::constant(2)]), new Alternative(['return' => Term::constant(99)])];
        (new ObservationLimit($context))->enforce(new SourceRef('test', 'fixture.php', 0, 1));
        self::assertCount(1, $context->normal);
        self::assertTrue((new Lattice())->contains($context->normal[0]->values['return'], Term::constant(99)));
        self::assertCount(2, $context->frontiers);
    }
    public function testJoinPreservesEqualFieldsAndIncludesAbsentFields(): void
    {
        $join = new ObservationLimit(\Tests\Fake\SolverFixture::context());
        $values = $join->join([['stable' => Term::constant('x'), 'optional' => Term::constant(2)], ['stable' => Term::constant('x')]]);
        self::assertSame('x', $values['stable']->native());
        self::assertSame('mixed', $values['optional']->attributes['type']);
    }
    public function testBoundaryRecordsBudgetAndCorrelationSeparately(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        (new ObservationLimit($context))->boundary(new SourceRef('test', 'fixture.php', 0, 1));
        self::assertSame(['BUDGET_EXCEEDED', 'CORRELATION_RELAXED'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testStorageWidensDifferentHeapFieldsAndKeepsCommonAliases(): void
    {
        $a = new StorageSnapshot(['x' => new Term('cell', 'a')], ['a' => Term::constant(1)]);
        $b = new StorageSnapshot(['x' => new Term('cell', 'a')], ['a' => Term::constant(2)]);
        $joined = (new ObservationLimit(\Tests\Fake\SolverFixture::context()))->storage([$a, $b]);
        self::assertSame('a', $joined->bindings['x']->literal);
        self::assertNotSame('constant', $joined->cells['a']->kind);
    }
}
