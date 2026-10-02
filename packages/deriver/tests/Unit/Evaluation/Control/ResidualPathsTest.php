<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Control\ResidualPaths;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Control\ResidualPaths
 */
#[CoversClass(ResidualPaths::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
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
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
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
#[UsesClass(Term::class)]
#[Small]
final class ResidualPathsTest extends TestCase
{
    public function testSealKeepsNormalAndExceptionalResidualsAndHavocsState(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $state = new State();
        $state->memory->write($state->local('x'), Term::constant(1));
        $paths = (new ResidualPaths($context))->seal($state, new SourceRef('test', 'fixture.php', 0, 1), 'depth');
        self::assertSame(['return', 'throw'], array_map(static fn (State $state): string => $state->completion->kind, $paths));
        self::assertSame('opaque', $paths[0]->snapshot()['x']->kind);
        self::assertTrue($paths[1]->completion->value?->attributes['uncertain']);
        self::assertSame('BUDGET_EXCEEDED', array_values($context->frontiers)[0]->code);
    }

    public function testInvocationHavocsOnlyStorageTheCalleeCanReach(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $state = new State();
        $caller = $state->memory->allocate(Term::constant('caller'));
        $shared = $state->memory->allocate(Term::constant('shared'));
        $state->locals['argument'] = $state->memory->allocate(Term::constant('copy'));
        $state->locals['reference'] = new Location($shared->root);
        $paths = (new ResidualPaths($context))->invocation($state, new SourceRef('test', 'fixture.php', 0, 1), 'recursive-specialization');
        self::assertSame(['return', 'throw'], array_map(static fn (State $state): string => $state->completion->kind, $paths));
        self::assertSame('caller', $paths[0]->memory->read($caller)->native());
        self::assertSame('opaque', $paths[0]->memory->read($shared)->kind);
        self::assertSame('BUDGET_EXCEEDED', $paths[0]->memory->unknownShared);
        self::assertSame(['BUDGET_EXCEEDED'], array_column(array_values($context->frontiers), 'code'));
    }

    public function testCompleteKeepsTheGivenResidualWithoutRecordingAnotherFrontier(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $state = new State();
        $state->constraints = ['x' => ['min' => 1, 'max' => null, 'equal' => null, 'excluded' => []]];
        $paths = (new ResidualPaths($context))->complete($state, Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE'));
        self::assertSame(['return', 'throw'], array_map(static fn (State $state): string => $state->completion->kind, $paths));
        self::assertSame('UNSUPPORTED_LANGUAGE_FEATURE', $paths[0]->completion->value?->literal);
        self::assertSame([], $paths[0]->constraints);
        self::assertSame([], $context->frontiers);
    }
}
