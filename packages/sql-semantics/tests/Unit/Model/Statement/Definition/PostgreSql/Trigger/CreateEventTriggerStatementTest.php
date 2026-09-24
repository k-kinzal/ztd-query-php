<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\DdlCommandTag;
use SqlSemantics\Model\Definition\Trigger\EventTriggerEvent;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateEventTriggerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateEventTriggerStatement::class)]
#[Medium]
final class CreateEventTriggerStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE EVENT TRIGGER x ON sql_drop WHEN TAG IN ('DROP TABLE') EXECUTE FUNCTION f()");
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Create, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EVENT TRIGGER x ON sql_drop EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame('CREATE EVENT TRIGGER "y" ON "sql_drop" EXECUTE FUNCTION "f"()', $statement->withName('y')->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithEventRejectsATagFilterOnLogin(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE EVENT TRIGGER x ON sql_drop WHEN TAG IN ('DROP TABLE') EXECUTE FUNCTION f()");
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame(EventTriggerEvent::DdlCommandEnd, $statement->withEvent(EventTriggerEvent::DdlCommandEnd)->event);
        $this->expectException(InvalidStructure::class);
        $statement->withEvent(EventTriggerEvent::Login);
    }

    public function testWithFunctionReplacesTheFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EVENT TRIGGER x ON sql_drop EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame(['s', 'g'], $statement->withFunction(new QualifiedName(['s', 'g']))->function->parts);
        self::assertSame(['f'], $statement->function->parts);
    }

    public function testWithTagsRejectsATagThatCannotRewriteATable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EVENT TRIGGER x ON table_rewrite EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame([DdlCommandTag::AlterType], $statement->withTags([DdlCommandTag::AlterType])->tags);
        $this->expectException(InvalidStructure::class);
        $statement->withTags([DdlCommandTag::DropTable]);
    }
}
