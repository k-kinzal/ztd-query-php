<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropTarget;

#[CoversClass(SequenceDropTarget::class)]
final class SequenceDropTargetTest extends TestCase
{
    public function testQuotedPreservesTheUnqualifiedAbsenceCoordinate(): void
    {
        $target = new SequenceDropTarget(new Identifier('Missing."棚"'), null, SequenceDropPresence::MissingSequence);

        self::assertSame('"Missing.""棚"""', $target->quoted());
    }
}
