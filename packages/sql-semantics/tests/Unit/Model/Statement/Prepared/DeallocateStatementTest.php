<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\DeallocateStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DeallocateStatement::class)]
final class DeallocateStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('DEALLOCATE PREPARE s', strict: false);
        self::assertInstanceOf(DeallocateStatement::class, $statement);
        self::assertSame('s', $statement->name);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::MySql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('DEALLOCATE PREPARE `s`', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
