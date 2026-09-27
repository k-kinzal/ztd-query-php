<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Summary;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Summary\CompletionRecord
 */
#[CoversClass(CompletionRecord::class)]
#[UsesClass(Completion::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(Term::class)]
#[Small]
final class CompletionRecordTest extends TestCase
{
    public function testInstantiateRebasesLocalWritesAndPreservesUnrelatedCells(): void
    {
        $state = new State();
        $state->locals['x'] = $state->memory->allocate(Term::constant(2));
        $state->completion = new Completion('return', Term::constant(2));
        $record = new CompletionRecord($state, 0);

        $entry = new State();
        $untouched = $entry->memory->allocate(Term::constant('caller'));
        $entry->locals['x'] = $entry->memory->allocate(Term::constant(1));
        $next = $record->instantiate($entry);
        self::assertSame('caller', $next->memory->read($untouched)->literal);
        self::assertSame(2, $next->memory->read($next->locals['x'])->literal);
        self::assertSame(1, $entry->memory->read($entry->locals['x'])->literal);
        self::assertSame($entry->memory->sequence, $next->memory->sequence);
    }
    public function testInstantiateKeepsSealedEffectsConservative(): void
    {
        $state = new State();
        $state->locals['x'] = $state->memory->allocate(Term::constant(2));
        $state->completion = new Completion('return', Term::constant(2));
        $record = new CompletionRecord($state, 0);

        $entry = new State();
        $entry->memory->cells['global:x'] = Term::constant('stale');
        $sealed = new CompletionRecord($state, 0, true);
        $next = $sealed->instantiate($entry);
        self::assertSame('opaque', $next->memory->cells['global:x']->kind);
    }
    public function testIdSeparatesReturnAndExceptionCorrelations(): void
    {
        $state = new State();
        $state->locals['x'] = $state->memory->allocate(Term::constant(2));
        $state->completion = new Completion('return', Term::constant(2));
        $record = new CompletionRecord($state, 0);

        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'TypeError'));
        self::assertNotSame($record->id(), (new CompletionRecord($exception, 0))->id());
        self::assertSame($record->id(), (new CompletionRecord($state->fork(), 7))->id());
    }
}
