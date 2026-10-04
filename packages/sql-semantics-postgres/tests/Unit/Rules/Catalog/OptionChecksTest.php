<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\OptionChecks::class)]
#[Medium]
final class OptionChecksTest extends TestCase
{
    public function testRedundantReportsARepeatedName(): void
    {
        self::assertSame('conflicting or redundant options', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE FOREIGN DATA WRAPPER w VALIDATOR a VALIDATOR b')->facts->diagnostics[0]->message());
    }

    public function testDatabaseAcceptsTheKnownOptions(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d OWNER a TEMPLATE t ENCODING \'UTF8\' LOCATION \'x\' TABLESPACE s CONNECTION LIMIT 5 icu_rules = \'\' strategy = file_copy')->facts->diagnostics);
    }

    public function testDatabaseReportsAnUnknownAlterOption(): void
    {
        self::assertSame('option "encoding" not recognized', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d WITH encoding x')->facts->diagnostics[0]->message());
    }
}
