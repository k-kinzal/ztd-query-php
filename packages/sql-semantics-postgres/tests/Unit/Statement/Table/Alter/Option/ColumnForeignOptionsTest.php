<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ColumnForeignOptions::class)]
#[Medium]
final class ColumnForeignOptionsTest extends TestCase
{
    public function testDeriveClauseChecksTheColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('ALTER FOREIGN TABLE t ALTER zz OPTIONS (ADD x \'y\')', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER FOREIGN TABLE f ALTER a OPTIONS (SET x \'y\', DROP z)', []);
        self::assertSame('ALTER FOREIGN TABLE f ALTER a OPTIONS (SET x \'y\', DROP z)', $statement->toString());
    }
}
