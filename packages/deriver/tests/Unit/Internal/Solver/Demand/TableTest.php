<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Demand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Demand\Table
 */
#[CoversClass(\Deriver\Internal\Solver\Demand\Table::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class TableTest extends TestCase
{
    public function testRegisterInternsOneDemandAndFreezesItsEntry(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        self::assertSame($cell, $table->register($key, $body, $entry));
        $entry->memory->cells['changed'] = \Deriver\Value\Term::constant(1);
        self::assertSame([], $cell->entry->memory->cells);
    }
    public function testContextsCountsUniqueSpecializations(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $body = \Tests\Fake\SummaryFixture::body($context);
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
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
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
        $table = $context->summaries;
        $cell = $table->register($key, $body, $entry);

        $table->stack[] = $key->id();
        $dependency = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, ['site']);
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
        $entry = new \Deriver\Internal\Solver\State();
        $key = (new \Deriver\Internal\Solver\Summary\Invocation())->key($body, $entry, []);
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
        $table = new \Deriver\Internal\Solver\Demand\Table();
        $table->owners = ['target' => 1,'target:default:X' => 2,'target:default:x' => 3,'closure:A.php:31' => 4,'closure:a.php:31' => 5];
        self::assertSame(1, $table->contexts('\\TARGET'));
        self::assertSame(2, $table->contexts('target:default:X'));
        self::assertSame(3, $table->contexts('target:default:x'));
        self::assertSame(4, $table->contexts('closure:A.php:31'));
        self::assertSame(5, $table->contexts('closure:a.php:31'));
    }
}
