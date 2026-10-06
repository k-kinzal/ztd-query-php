<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Family;

#[CoversClass(Family::class)]
#[Small]
final class FamilyTest extends TestCase
{
    public function testCasesAreTheFamiliesThatOwnStatements(): void
    {
        self::assertCount(7, Family::cases());
    }
}
