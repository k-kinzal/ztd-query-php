<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Rendering\TableDefinitionText;

#[CoversClass(TableDefinitionText::class)]
#[Medium]
final class TableDefinitionTextTest extends TestCase
{
    public function testRenderWritesTheExecutableAndTheStoredDefinitionAlike(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS d.t (a INT, b INT) ENGINE = InnoDB COMMENT = \'x\'', $semantics->analyze("CREATE TEMPORARY TABLE IF NOT EXISTS d.t (a INT, b INT) ENGINE = InnoDB COMMENT = 'x'")->toString());
        self::assertSame('CREATE PROCEDURE p() CREATE TABLE t (a INT, a INT) ENGINE = InnoDB', $semantics->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT, a INT) ENGINE = InnoDB')->toString());
    }

    public function testRenderWritesTheEnclosedLegacyForm(): void
    {
        $semantics = new Semantics(Dialect::MySql, '5.6.51');

        self::assertSame('CREATE TABLE t (PARTITION BY HASH (a) SELECT 1 AS a) UNION SELECT 2 ORDER BY 1', $semantics->analyze('CREATE TABLE t (PARTITION BY HASH (a) SELECT 1 AS a) UNION SELECT 2 ORDER BY 1')->toString());
    }
}
