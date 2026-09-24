<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Trigger\ChangeReactions;

#[CoversClass(ChangeReactions::class)]
#[Medium]
final class ChangeReactionsTest extends TestCase
{
    #[TestWith(['CREATE TRIGGER x AFTER INSERT ON t EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE EVENT TRIGGER x ON sql_drop EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE POLICY p ON t'])]
    #[TestWith(['ALTER POLICY p ON t'])]
    #[TestWith(['CREATE RULE r AS ON INSERT TO t DO NOTHING'])]
    #[TestWith(['CREATE PUBLICATION p FOR ALL TABLES'])]
    #[TestWith(['DROP SUBSCRIPTION s'])]
    public function testWriteCoversEachForm(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        self::assertNotNull(ChangeReactions::write($binder->bind($sql)));
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        self::assertNull(ChangeReactions::write($binder->bind('SELECT 1')));
    }
}
