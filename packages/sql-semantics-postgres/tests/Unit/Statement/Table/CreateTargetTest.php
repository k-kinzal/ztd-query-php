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
    public function testDeriveClauseDerivesStorageParameterModifiers(): void
    {
        $sql = 'CREATE TEMP TABLE n WITH (opt = custom_type (DEFAULT)) AS SELECT 1';
        $statement = (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect($sql);
        self::assertSame($sql, $statement->toString());
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->analyze('CREATE TABLE n (x, y) USING heap WITH (fillfactor = 70) ON COMMIT PRESERVE ROWS TABLESPACE s AS SELECT 1, 2', []);
        self::assertSame('CREATE TABLE n (x, y) USING heap WITH (fillfactor = 70) ON COMMIT PRESERVE ROWS TABLESPACE s AS SELECT 1, 2', $statement->toString());
    }
}
