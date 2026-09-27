<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Demand;

use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Invocation;
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
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
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
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Demand\Table
 */
#[CoversClass(Table::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(Resources::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Key::class)]
#[UsesClass(State::class)]
#[UsesClass(Invocation::class)]
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
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
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
#[UsesClass(Term::class)]
#[Small]
final class TableTest extends TestCase
{
    public function testRegisterInternsOneDemandAndFreezesItsEntry(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        self::assertSame($cell, $table->register($key, $body, $entry));
        $entry->memory->cells['changed'] = Term::constant(1);
        self::assertSame([], $cell->entry->memory->cells);
    }
    public function testContextsCountsUniqueSpecializations(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $table->register($key, $body, $entry);
        self::assertSame(1, $table->contexts('TARGET'));
        self::assertSame(0, $table->contexts('absent'));
    }
    public function testInvalidateSchedulesCompletedDependents(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $table->stack[] = $key->id();
        $dependency = (new Invocation())->key($body, $entry, ['site']);
        $child = $table->register($dependency, $body, $entry);
        $cell->status = 'stable';
        $table->invalidate($child);
        self::assertSame('pending', $cell->status);
        self::assertArrayHasKey($key->id(), $child->dependents);
    }
    public function testReadyDoesNotConfuseRunningWithCompletedBottom(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new State();
        $key = (new Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $table->stack[] = $key->id();
        $table->register($key, $body, $entry);
        $cell->status = 'running';
        self::assertFalse($table->ready($cell));
        $cell->status = 'frontier';
        self::assertTrue($table->ready($cell));
    }
    public function testContextsKeepsCaseSensitiveOwnersSeparateFromNamedPhpAliases(): void
    {
        $table = new Table();
        $table->owners = ['target' => 1,'target:default:X' => 2,'target:default:x' => 3,'closure:A.php:31' => 4,'closure:a.php:31' => 5];
        self::assertSame(1, $table->contexts('\\TARGET'));
        self::assertSame(2, $table->contexts('target:default:X'));
        self::assertSame(3, $table->contexts('target:default:x'));
        self::assertSame(4, $table->contexts('closure:A.php:31'));
        self::assertSame(5, $table->contexts('closure:a.php:31'));
    }
}
