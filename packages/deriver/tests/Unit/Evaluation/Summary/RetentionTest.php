<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Summary;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Retention;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class RetentionTest extends TestCase
{
    public function testSmallBoundsRetainedArrayNodes(): void
    {
        $state = new State();
        $state->completion = new Completion('return', Term::fromNative(range(0, 8191)));
        self::assertFalse((new Retention())->small(new CompletionRecord($state, 0)));
        $state->completion = new Completion('return', Term::constant('small'));
        self::assertTrue((new Retention())->small(new CompletionRecord($state, 0)));
    }

    public function testCompactRetainsLocalValuesWithoutCallerStorage(): void
    {
        $state = new State();
        $state->memory->allocate(Term::constant('caller'));
        $state->memory->write($state->local('value'), Term::constant('local'));
        $state->registers['temporary'] = Term::constant('discard');
        $state->completion = new Completion('return', Term::constant(7));
        $record = (new Retention())->compact(new CompletionRecord($state, 1));
        self::assertCount(1, $record->state->memory->cells);
        self::assertSame([], $record->state->registers);
        self::assertSame('local', $record->state->memory->read($record->state->local('value'))->native());
        self::assertSame(7, $record->instantiate(new State())->completion->value?->native());
    }
    public function testEvidenceKeepsTransitiveParentsWithoutUnrelatedCallerNodes(): void
    {
        $at = new \Deriver\Reference\SourceRef('test', 'a.php', 0, 1);
        $state = new State();
        $state->evidence = ['result'];
        $nodes = [
            'caller' => new \Deriver\Result\Derivation('caller', 'call', $at),
            'input' => new \Deriver\Result\Derivation('input', 'data', $at),
            'result' => new \Deriver\Result\Derivation('result', 'data', $at, ['input']),
        ];
        $retained = (new Retention())->evidence([new CompletionRecord($state, 0)], $nodes);
        self::assertNotNull($retained);
        self::assertSame(['result', 'input'], array_keys($retained));
    }

}
