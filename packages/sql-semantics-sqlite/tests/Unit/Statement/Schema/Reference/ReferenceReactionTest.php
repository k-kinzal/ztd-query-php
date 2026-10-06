<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceReaction;

#[CoversClass(ReferenceReaction::class)]
#[Small]
final class ReferenceReactionTest extends TestCase
{
    public function testCasesSpellTheKeywordsOfEachReaction(): void
    {
        self::assertSame(['SET NULL', 'SET DEFAULT', 'CASCADE', 'RESTRICT', 'NO ACTION'], array_column(ReferenceReaction::cases(), 'value'));
    }
}
