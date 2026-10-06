<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids;

#[CoversClass(AnonymousGtids::class)]
#[Small]
final class AnonymousGtidsTest extends TestCase
{
    public function testCasesSpellTheKeywordSettings(): void
    {
        self::assertSame(['OFF', 'LOCAL'], array_column(AnonymousGtids::cases(), 'value'));
    }
}
