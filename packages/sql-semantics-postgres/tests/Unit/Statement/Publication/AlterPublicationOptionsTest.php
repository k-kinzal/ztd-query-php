<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Publication\AlterPublicationOptions::class)]
#[Medium]
final class AlterPublicationOptionsTest extends TestCase
{
    public function testRenderWritesSet(): void
    {
        self::assertSame('ALTER PUBLICATION p SET (publish = \'update\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p SET (publish = \'update\')')->toString());
    }

    public function testDeriveStatementDerivesTheValues(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p SET (publish_via_partition_root)')->facts->diagnostics);
    }
}
