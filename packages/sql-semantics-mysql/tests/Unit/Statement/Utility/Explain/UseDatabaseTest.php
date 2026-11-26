<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UseDatabase::class)]
#[Medium]
final class UseDatabaseTest extends TestCase
{
    public function testDeriveStatementReturnsNoRows(): void
    {
        $use = (new Inspection())->inspect('USE db');
        self::assertInstanceOf(UseDatabase::class, $use->statement);
        self::assertNull($use->shape());
    }

    public function testRenderWritesTheDatabase(): void
    {
        self::assertSame('USE db', (new Inspection('mysql-5.7.44'))->inspect('use db')->toString());
    }
}
