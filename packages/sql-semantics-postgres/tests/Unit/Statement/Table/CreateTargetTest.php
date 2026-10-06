<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTarget::class)]
#[Medium]
final class CreateTargetTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (x, y) USING heap WITH (fillfactor = 70) ON COMMIT PRESERVE ROWS TABLESPACE s AS SELECT 1, 2', []);
        self::assertSame('CREATE TABLE n (x, y) USING heap WITH (fillfactor = 70) ON COMMIT PRESERVE ROWS TABLESPACE s AS SELECT 1, 2', $statement->toString());
    }
}
