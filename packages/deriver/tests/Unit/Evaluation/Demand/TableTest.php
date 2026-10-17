<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Demand;

use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Demand\Table
 */
#[CoversClass(Table::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Query\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(State::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Project\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringCache::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweredCallable::class)]
#[UsesClass(\Deriver\Source\Lowering\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Parsing\ParseCache::class)]
#[UsesClass(\Deriver\Source\Parsing\SourceParser::class)]
#[UsesClass(\Deriver\Source\Parsing\ParsedFile::class)]
#[UsesClass(\Deriver\Source\Lowering\CallableLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Lowering\InstructionEmitter::class)]
#[UsesClass(\Deriver\Source\Lowering\LoweringScope::class)]
#[UsesClass(\Deriver\Source\Lowering\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\Lowering\LineMap::class)]
#[UsesClass(\Deriver\Source\Parsing\MagicContext::class)]
#[UsesClass(\Deriver\Source\Parsing\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Parsing\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Parsing\ClassScope::class)]
#[UsesClass(\Deriver\Source\Parsing\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
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
