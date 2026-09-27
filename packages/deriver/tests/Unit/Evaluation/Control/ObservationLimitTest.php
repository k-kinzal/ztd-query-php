<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Frontier;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Identity;
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
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Identity::class)]
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
