<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Trigger\EventTriggerCreation;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Trigger\DdlCommandTag;
use SqlSemantics\Model\Definition\Trigger\EventTriggerEvent;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateEventTriggerStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EventTriggerCreation::class)]
#[Medium]
final class EventTriggerCreationTest extends TestCase
{
    public function testBindReadsTheEventAndFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EVENT TRIGGER "Guard" ON Ddl_Command_Start EXECUTE PROCEDURE audit.stop()');
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame('Guard', $statement->name);
        self::assertSame(EventTriggerEvent::DdlCommandStart, $statement->event);
        self::assertSame(['audit', 'stop'], $statement->function->parts);
        self::assertSame([], $statement->tags);
    }

    #[TestWith(['CREATE EVENT TRIGGER x ON "SQL_DROP" EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE EVENT TRIGGER x ON ddl_command_start WHEN kind IN (\'GRANT\') EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE EVENT TRIGGER x ON ddl_command_start WHEN tag IN (\'GRANT\') AND tag IN (\'REVOKE\') EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE EVENT TRIGGER x ON login WHEN tag IN (\'GRANT\') EXECUTE FUNCTION f()'])]
    #[TestWith(['CREATE EVENT TRIGGER x ON table_rewrite WHEN tag IN (\'GRANT\') EXECUTE FUNCTION f()'])]
    public function testBindDiagnosesAnEventTriggerThePostgreSqlServerRejects(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TriggerDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith(["'create table'"])]
    #[TestWith(["E'CREATE\\x20TABLE'"])]
    #[TestWith(['$$Create Table$$'])]
    public function testTagsMatchWithoutRegardToCase(string $tag): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EVENT TRIGGER x ON ddl_command_end WHEN TAG IN (' . $tag . ', \'drop table\') EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame([DdlCommandTag::CreateTable, DdlCommandTag::DropTable], $statement->tags);
    }

    #[TestWith(["'SELECT'"])]
    #[TestWith(["'text'"])]
    public function testTagsRejectCommandsWithoutEventTriggers(string $tag): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE EVENT TRIGGER x ON ddl_command_end WHEN TAG IN (' . $tag . ') EXECUTE FUNCTION f()');
    }
}
