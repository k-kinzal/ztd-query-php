<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePersistence;

#[CoversClass(SequencePersistence::class)]
final class SequencePersistenceTest extends TestCase
{
    public function testTemporaryIncludesTheNativeDiagnosticVariant(): void
    {
        self::assertFalse(SequencePersistence::Permanent->temporary());
        self::assertFalse(SequencePersistence::Unlogged->temporary());
        self::assertTrue(SequencePersistence::Temporary->temporary());
        self::assertTrue(SequencePersistence::GlobalTemporary->temporary());
    }
}
