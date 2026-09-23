<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\DeallocateAllStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DeallocateAllStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DeallocateAllStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DEALLOCATE ALL', strict: false);
        self::assertInstanceOf(DeallocateAllStatement::class, $statement);
        self::assertFalse(property_exists($statement, 'name'));
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::PostgreSql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('DEALLOCATE ALL', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
