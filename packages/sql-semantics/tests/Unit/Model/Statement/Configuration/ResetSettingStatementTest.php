<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\ResetSettingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResetSettingStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ResetSettingStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('RESET PERSIST IF EXISTS max_connections', strict: false);
        self::assertInstanceOf(ResetSettingStatement::class, $statement);
        self::assertSame(['max_connections'], $statement->setting->name);
        self::assertTrue($statement->setting->ifExists);
        self::assertSame(\SqlSemantics\Model\Configuration\SettingScope::Persist, $statement->setting->scope);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::MySql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('RESET PERSIST IF EXISTS `max_connections`', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
