<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\ResetAllSettingsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResetAllSettingsStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ResetAllSettingsStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('RESET ALL', strict: false);
        self::assertInstanceOf(ResetAllSettingsStatement::class, $statement);
        self::assertFalse(property_exists($statement, 'setting'));
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::PostgreSql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('RESET ALL', $changed->toString());
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($changed->toString(), strict: false)));
    }
}
