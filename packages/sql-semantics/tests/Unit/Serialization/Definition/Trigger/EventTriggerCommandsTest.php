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

#[CoversClass(\SqlSemantics\Serialization\Definition\Trigger\EventTriggerCommands::class)]
#[Medium]
final class EventTriggerCommandsTest extends TestCase
{
    #[TestWith(['ALTER EVENT TRIGGER audit ENABLE ALWAYS'])]
    #[TestWith(['ALTER EVENT TRIGGER audit DISABLE'])]
    #[TestWith(['ALTER EVENT TRIGGER audit RENAME TO "x""y"'])]
    #[TestWith(['ALTER EVENT TRIGGER audit OWNER TO CURRENT_ROLE'])]
    #[TestWith(['ALTER EVENT TRIGGER audit OWNER TO "CURRENT_ROLE"'])]
    #[TestWith(['DROP EVENT TRIGGER audit, "IF"'])]
    #[TestWith(['DROP EVENT TRIGGER IF EXISTS audit CASCADE'])]
    public function testWriteRetainsTheConcreteRequestAndOperands(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        $rebound = $binder->bind($statement->toString());
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($statement->kind, $rebound->kind);
        self::assertSame($statement->toString(), $rebound->toString());
    }

    #[TestWith(['DROP TABLE t'])]
    #[TestWith(['SELECT 1'])]
    public function testWriteReturnsNullForOtherSemanticFamilies(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id integer)'));
        self::assertNull(\SqlSemantics\Serialization\Definition\Trigger\EventTriggerCommands::write($binder->bind($sql)));
    }

}
