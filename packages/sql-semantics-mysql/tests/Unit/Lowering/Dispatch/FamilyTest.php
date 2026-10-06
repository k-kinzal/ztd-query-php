<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dispatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\Family;

#[CoversClass(Family::class)]
#[Small]
final class FamilyTest extends TestCase
{
    public function testCasesNameEveryFamilyAStatementIsRoutedTo(): void
    {
        self::assertCount(10, Family::cases());
        self::assertContains(Family::Definition, Family::cases());
        self::assertContains(Family::Query, Family::cases());
    }
}
