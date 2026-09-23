<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;

#[CoversClass(DatabaseReadOnly::class)]
#[Medium]
final class DatabaseReadOnlyTest extends TestCase
{
    public function testCasesDefineOnlyTheApplicablePolicies(): void
    {
        self::assertSame([DatabaseReadOnly::Enabled, DatabaseReadOnly::Disabled], DatabaseReadOnly::cases());
    }
}
