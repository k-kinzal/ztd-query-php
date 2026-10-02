<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Query\Budget;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
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
        self::assertCount(2, $context->normal);
        self::assertSame(1, $context->normal[0]->values['return']->native());
        self::assertTrue((new Lattice())->contains($context->normal[1]->values['return'], Term::constant(2)));
        self::assertTrue((new Lattice())->contains($context->normal[1]->values['return'], Term::constant(99)));
        self::assertCount(2, $context->frontiers);
    }
    public function testDistinctMergesOnlyIdenticalValuesStateAndStorage(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $limit = new ObservationLimit($context);
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $outcomes = [new Alternative(['v' => Term::constant(1)], ['g' => true]), new Alternative(['v' => Term::constant(1)], ['g' => true]), new Alternative(['v' => Term::constant(1)], state: ['x' => Term::constant(2)])];
        $distinct = $limit->distinct($outcomes, $source);
        self::assertCount(2, $distinct);
        self::assertSame(['g' => true], $distinct[0]->guard);
        self::assertSame([], $context->frontiers);
    }
    public function testDistinctExceptionsKeepsDifferentExceptionClasses(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $outcomes = [new Exceptional(new Term('throwable', 'Error'), ['g' => true]), new Exceptional(new Term('throwable', 'Error'), ['g' => false]), new Exceptional(new Term('throwable', 'TypeError'))];
        $distinct = (new ObservationLimit($context))->distinctExceptions($outcomes, $source);
        self::assertSame(['Error', 'TypeError'], array_map(static fn (Exceptional $outcome): string|int|float|bool|null => $outcome->exception->literal, $distinct));
        self::assertSame([], $distinct[0]->guard);
        self::assertSame(['CORRELATION_RELAXED'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testKeySeparatesValuesStateAndStorage(): void
    {
        $limit = new ObservationLimit(\Tests\Fake\SolverFixture::context());
        $base = $limit->key(['v' => Term::constant(1)], [], new StorageSnapshot());
        self::assertSame($base, $limit->key(['v' => Term::constant(1)], [], new StorageSnapshot()));
        self::assertNotSame($base, $limit->key(['v' => Term::constant('1')], [], new StorageSnapshot()));
        self::assertNotSame($base, $limit->key([], ['v' => Term::constant(1)], new StorageSnapshot()));
        self::assertNotSame($base, $limit->key(['v' => Term::constant(1)], [], new StorageSnapshot(cells: ['c' => Term::constant(1)])));
    }
    public function testGuardKeepsSharedEntriesAndReportsRelaxation(): void
    {
        $source = new SourceRef('test', 'fixture.php', 0, 1);
        $equal = \Tests\Fake\SolverFixture::context();
        self::assertSame(['a' => true], (new ObservationLimit($equal))->guard(['a' => true], ['a' => true], $source));
        self::assertSame([], $equal->frontiers);
        $different = \Tests\Fake\SolverFixture::context();
        self::assertSame(['a' => true], (new ObservationLimit($different))->guard(['a' => true, 'b' => true], ['a' => true, 'b' => false], $source));
        self::assertSame(['CORRELATION_RELAXED'], array_column(array_values($different->frontiers), 'code'));
    }
    public function testEnforceMergesRepeatedOutcomesBeforeJoining(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget: new Budget(partitions: 2));
        $context->normal = [new Alternative(['return' => Term::constant(1)], ['a' => true, 'b' => true], evidence: ['x']), new Alternative(['return' => Term::constant(2)]), new Alternative(['return' => Term::constant(1)], ['a' => true, 'b' => false], evidence: ['y'])];
        (new ObservationLimit($context))->enforce(new SourceRef('test', 'fixture.php', 0, 1));
        self::assertSame([1, 2], array_map(static fn (Alternative $outcome): string|int|float|bool|null => $outcome->values['return']->literal, $context->normal));
        self::assertSame(['a' => true], $context->normal[0]->guard);
        self::assertSame(['x', 'y'], $context->normal[0]->evidence);
        self::assertSame(['CORRELATION_RELAXED'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testEnforceKeepsIdenticalOutcomesWithinTheBudget(): void
    {
        $context = \Tests\Fake\SolverFixture::context(budget: new Budget(partitions: 2));
        $context->normal = [new Alternative(['return' => Term::constant(1)]), new Alternative(['return' => Term::constant(1)])];
        (new ObservationLimit($context))->enforce(new SourceRef('test', 'fixture.php', 0, 1));
        self::assertCount(2, $context->normal);
        self::assertSame([], $context->frontiers);
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
