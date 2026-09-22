<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\ResetAllPersistedVariablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResetAllPersistedVariablesStatement::class)]
final class ResetAllPersistedVariablesStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('RESET PERSIST', strict: false);
        self::assertInstanceOf(ResetAllPersistedVariablesStatement::class, $statement);
        self::assertFalse(property_exists($statement, 'setting'));
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::MySql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('RESET PERSIST', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
