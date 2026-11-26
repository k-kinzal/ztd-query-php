<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwnerKind;

#[CoversClass(SequenceOwnerKind::class)]
final class SequenceOwnerKindTest extends TestCase
{
    public function testCasesPreserveTheEligibleRelationKinds(): void
    {
        self::assertSame(['table', 'foreign', 'view', 'partitioned'], array_column(SequenceOwnerKind::cases(), 'value'));
    }
}
