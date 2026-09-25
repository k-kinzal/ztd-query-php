<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\Presence;

#[CoversClass(Presence::class)]
final class PresenceTest extends TestCase
{
    public function testCasesDistinguishExistenceFromValues(): void
    {
        self::assertSame(['present', 'absent', 'maybe'], array_column(Presence::cases(), 'value'));
    }
}
