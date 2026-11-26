<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\CreateExtension::class)]
#[Medium]
final class CreateExtensionTest extends TestCase
{
    public function testRenderDropsTheNoiseWith(): void
    {
        self::assertSame('CREATE EXTENSION IF NOT EXISTS hstore SCHEMA ext CASCADE', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE EXTENSION IF NOT EXISTS hstore WITH SCHEMA ext CASCADE')->toString());
    }

    public function testDeriveStatementReportsFrom(): void
    {
        self::assertSame('CREATE EXTENSION ... FROM is no longer supported', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE EXTENSION e FROM \'1.0\'')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsARepeatedOption(): void
    {
        self::assertSame('conflicting or redundant options', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE EXTENSION e SCHEMA a SCHEMA b')->facts->diagnostics[0]->message());
    }
}
