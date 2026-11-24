<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropPresence;

#[CoversClass(SequenceDropPresence::class)]
final class SequenceDropPresenceTest extends TestCase
{
    public function testCasesDistinguishTheTwoAbsences(): void
    {
        self::assertSame(
            ['existing', 'missing-sequence', 'missing-namespace'],
            array_column(SequenceDropPresence::cases(), 'value'),
        );
    }
}
