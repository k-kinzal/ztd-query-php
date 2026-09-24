<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Definition\Sequence\SequencePersistence;

#[CoversClass(SequencePersistence::class)]
#[Small]
final class SequencePersistenceTest extends TestCase
{
    public function testSpellsEachPersistenceAsItsKeyword(): void
    {
        self::assertSame(['', 'TEMPORARY', 'UNLOGGED'], array_column(SequencePersistence::cases(), 'value'));
    }
}
