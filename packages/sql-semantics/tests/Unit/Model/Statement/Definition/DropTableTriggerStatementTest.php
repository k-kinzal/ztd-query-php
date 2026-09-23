<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTableTriggerStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DropTableTriggerStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP TRIGGER IF EXISTS tr ON public.t CASCADE', strict: false);
        self::assertInstanceOf(DropTableTriggerStatement::class, $statement);
        self::assertSame('tr', $statement->name);
        self::assertSame(['public', 't'], $statement->table->parts);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Cascade, $statement->behavior);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::PostgreSql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('DROP TRIGGER IF EXISTS "tr" ON "public"."t" CASCADE', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }
}
