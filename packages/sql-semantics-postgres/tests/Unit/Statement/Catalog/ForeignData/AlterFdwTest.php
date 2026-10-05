<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\AlterFdw::class)]
#[Medium]
final class AlterFdwTest extends TestCase
{
    public function testRenderWritesTheChanges(): void
    {
        self::assertSame('ALTER FOREIGN DATA WRAPPER w NO VALIDATOR OPTIONS (SET a \'b\', DROP c)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER FOREIGN DATA WRAPPER w NO VALIDATOR OPTIONS (SET a \'b\', DROP c)')->toString());
    }

    public function testDeriveStatementAcceptsOneOfEach(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER FOREIGN DATA WRAPPER w HANDLER h VALIDATOR v')->facts->diagnostics);
    }

    public function testRejectsAnEmptyChange(): void
    {
        $this->expectExceptionMessage('ALTER FOREIGN DATA WRAPPER changes a function or an option.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\AlterFdw(new \SqlSemantics\Statement\Identifier\Name('w'));
    }
}
