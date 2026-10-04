<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\RoutineMatch;

#[CoversClass(RoutineMatch::class)]
#[Small]
final class RoutineMatchTest extends TestCase
{
    public function testFindAcceptsOnlyExactMatchesWhenEverySchemaIsSearched(): void
    {
        $match = new RoutineMatch();
        self::assertSame('text', $match->find('upper', ['unknown'], false)[0] ?? null);
        self::assertNull($match->find('upper', ['varchar'], false));
        self::assertSame('text', $match->find('upper', ['varchar'], true)[0] ?? null);
    }

    public function testFindLeavesAnAmbiguousCallUndecided(): void
    {
        self::assertNull((new RoutineMatch())->find('extract', ['unknown', 'unknown'], true));
    }

    public function testPreferredKeepsTextForUnknownArguments(): void
    {
        $rows = [['text', 's', 'P', ['text']], ['bytea', 's', 'P', ['bytea']]];
        self::assertSame([$rows[0]], (new RoutineMatch())->preferred($rows, ['unknown']));
    }

    public function testSingleAnswersOnlyAUniqueCandidate(): void
    {
        $row = ['text', 's', 'P', ['text']];
        self::assertSame([$row, null], [(new RoutineMatch())->single([$row]), (new RoutineMatch())->single([$row, $row])]);
    }
}
