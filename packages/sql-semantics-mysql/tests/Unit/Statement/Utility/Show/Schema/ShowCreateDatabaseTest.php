<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase;

#[CoversClass(ShowCreateDatabase::class)]
#[Medium]
final class ShowCreateDatabaseTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW CREATE DATABASE db');
        self::assertInstanceOf(ShowCreateDatabase::class, $show->statement);
        self::assertSame('Database', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE DATABASE db', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW CREATE DATABASE db')->toString());
    }
}
