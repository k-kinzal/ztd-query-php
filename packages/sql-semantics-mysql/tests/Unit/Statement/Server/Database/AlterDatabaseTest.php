<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterDatabase::class)]
#[Medium]
final class AlterDatabaseTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('ALTER DATABASE d COLLATE utf8mb4_bin', (new Inspection('mysql-5.7.44'))->inspect('alter schema d collate utf8mb4_bin')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('ALTER DATABASE d COLLATE utf8mb4_bin');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
