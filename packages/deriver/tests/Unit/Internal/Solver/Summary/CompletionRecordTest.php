<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Summary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Summary\CompletionRecord
 */
#[CoversClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Havoc::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class CompletionRecordTest extends TestCase
{
    public function testInstantiateRebasesLocalWritesAndPreservesUnrelatedCells(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->locals['x'] = $state->memory->allocate(\Deriver\Value\Term::constant(2));
        $state->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(2));
        $record = new \Deriver\Internal\Solver\Summary\CompletionRecord($state, 0);

        $entry = new \Deriver\Internal\Solver\State();
        $untouched = $entry->memory->allocate(\Deriver\Value\Term::constant('caller'));
        $entry->locals['x'] = $entry->memory->allocate(\Deriver\Value\Term::constant(1));
        $next = $record->instantiate($entry);
        self::assertSame('caller', $next->memory->read($untouched)->literal);
        self::assertSame(2, $next->memory->read($next->locals['x'])->literal);
        self::assertSame(1, $entry->memory->read($entry->locals['x'])->literal);
        self::assertSame($entry->memory->sequence, $next->memory->sequence);
    }
    public function testInstantiateKeepsSealedEffectsConservative(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->locals['x'] = $state->memory->allocate(\Deriver\Value\Term::constant(2));
        $state->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(2));
        $record = new \Deriver\Internal\Solver\Summary\CompletionRecord($state, 0);

        $entry = new \Deriver\Internal\Solver\State();
        $entry->memory->cells['global:x'] = \Deriver\Value\Term::constant('stale');
        $sealed = new \Deriver\Internal\Solver\Summary\CompletionRecord($state, 0, true);
        $next = $sealed->instantiate($entry);
        self::assertSame('opaque', $next->memory->cells['global:x']->kind);
    }
    public function testIdSeparatesReturnAndExceptionCorrelations(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->locals['x'] = $state->memory->allocate(\Deriver\Value\Term::constant(2));
        $state->completion = new \Deriver\Internal\Solver\Completion('return', \Deriver\Value\Term::constant(2));
        $record = new \Deriver\Internal\Solver\Summary\CompletionRecord($state, 0);

        $exception = $state->fork();
        $exception->completion = new \Deriver\Internal\Solver\Completion('throw', new \Deriver\Value\Term('throwable', 'TypeError'));
        self::assertNotSame($record->id(), (new \Deriver\Internal\Solver\Summary\CompletionRecord($exception, 0))->id());
        self::assertSame($record->id(), (new \Deriver\Internal\Solver\Summary\CompletionRecord($state->fork(), 7))->id());
    }
}
