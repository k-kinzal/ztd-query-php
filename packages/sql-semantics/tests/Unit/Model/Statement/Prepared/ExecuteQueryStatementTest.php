<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\ExecuteQueryStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExecuteQueryStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExecuteQueryStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('EXECUTE s(1, 2)', strict: false);
        self::assertInstanceOf(ExecuteQueryStatement::class, $statement);
        self::assertCount(2, $statement->arguments);
        self::assertSame('1', $statement->arguments[0]->spelling());
        self::assertSame('2', $statement->arguments[1]->spelling());
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::PostgreSql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('EXECUTE "s"(1, 2)', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
