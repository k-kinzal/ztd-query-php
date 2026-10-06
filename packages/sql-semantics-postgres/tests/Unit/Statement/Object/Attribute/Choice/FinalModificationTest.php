<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\FinalModification;

#[CoversClass(FinalModification::class)]
#[Small]
final class FinalModificationTest extends TestCase
{
    public function testReadComparesExactly(): void
    {
        self::assertSame([FinalModification::ReadOnly, FinalModification::Shareable, FinalModification::ReadWrite, null], [FinalModification::read('read_only'), FinalModification::read('shareable'), FinalModification::read('read_write'), FinalModification::read('READ_ONLY')]);
    }
}
