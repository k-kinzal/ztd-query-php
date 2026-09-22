<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\ExecuteUsingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExecuteUsingStatement::class)]
final class ExecuteUsingStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('EXECUTE s USING @x, @y', strict: false);
        self::assertInstanceOf(ExecuteUsingStatement::class, $statement);
        self::assertCount(2, $statement->variables);
        self::assertSame('x', $statement->variables[0]->spelling());
        self::assertSame('y', $statement->variables[1]->spelling());
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::MySql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('EXECUTE `s` USING @`x`, @`y`', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
