<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\CreateFdw::class)]
#[Medium]
final class CreateFdwTest extends TestCase
{
    public function testRenderWritesFunctionsAndOptions(): void
    {
        self::assertSame('CREATE FOREIGN DATA WRAPPER w HANDLER h NO VALIDATOR OPTIONS (debug \'true\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE FOREIGN DATA WRAPPER w HANDLER h NO VALIDATOR OPTIONS (debug \'true\')')->toString());
    }

    public function testDeriveStatementReportsATwiceNamedHandler(): void
    {
        self::assertSame('conflicting or redundant options', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE FOREIGN DATA WRAPPER w HANDLER a HANDLER b')->facts->diagnostics[0]->message());
    }
}
