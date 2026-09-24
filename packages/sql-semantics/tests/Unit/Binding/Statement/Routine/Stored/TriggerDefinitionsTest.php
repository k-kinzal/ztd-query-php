<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Stored\TriggerDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\EmbeddedStatement;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateTriggerStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerDefinitions::class)]
#[Medium]
final class TriggerDefinitionsTest extends TestCase
{
    public function testBindResolvesRowImagesInOrdinaryStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT, n INT); CREATE TABLE audit (id INT, n INT)'));
        $statement = $binder->bind('CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW UPDATE audit SET n = NEW.n WHERE id = OLD.id');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertInstanceOf(EmbeddedStatement::class, $statement->body);
        self::assertInstanceOf(UpdateTableStatement::class, $statement->body->statement);
        $columns = array_values(array_filter(\SqlSemantics\Model\Traversal\Expressions::all($statement), static fn ($expression): bool => $expression instanceof TriggerColumn));
        self::assertEqualsCanonicalizing([RowVersion::New, RowVersion::Old], array_map(static fn (TriggerColumn $column): RowVersion => $column->version, $columns));
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindDiagnosesTablesInAnotherDatabase(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW DO 1', strict: false);
    }

    public function testBindDiagnosesAnOldRowInAnInsertTrigger(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO OLD.n', strict: false);
    }
}
