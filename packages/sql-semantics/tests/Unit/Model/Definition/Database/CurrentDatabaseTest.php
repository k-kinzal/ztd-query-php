<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\CurrentDatabase;

#[CoversClass(CurrentDatabase::class)]
#[Medium]
final class CurrentDatabaseTest extends TestCase
{
    public function testCasesDefineOnlyTheApplicablePolicies(): void
    {
        self::assertSame([CurrentDatabase::Session], CurrentDatabase::cases());
    }
}
