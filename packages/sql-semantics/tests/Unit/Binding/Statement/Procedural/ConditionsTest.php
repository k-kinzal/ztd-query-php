<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\Conditions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Statement\Procedural\ResignalStatement;
use SqlSemantics\Model\Statement\Procedural\SignalStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Conditions::class)]
#[Medium]
final class ConditionsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsStateAndItemsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SIGNAL SQLSTATE VALUE '45000' SET MESSAGE_TEXT = _binary 'x', MYSQL_ERRNO = @errno, TABLE_NAME = TIME '10:00:00'", strict: false);
        self::assertInstanceOf(SignalStatement::class, $statement);
        self::assertSame([ConditionItem::MessageText, ConditionItem::MySqlErrorNumber, ConditionItem::TableName], array_column($statement->assignments, 'item'));
        self::assertInstanceOf(UnresolvedVariableReference::class, $statement->assignments[1]->value);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)));
        $resignal = $binder->bind('RESIGNAL');
        self::assertInstanceOf(ResignalStatement::class, $resignal);
        self::assertSame([], $resignal->assignments);
    }

    #[TestWith(["SIGNAL SQLSTATE '00000'"])]
    #[TestWith(["SIGNAL SQLSTATE 'text'"])]
    #[TestWith(["SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = 'b'"])]
    #[TestWith(['RESIGNAL SET MYSQL_ERRNO = NULL'])]
    public function testBindDiagnosesImpossibleSignalInformation(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SignalInformation->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    public function testStateRejectsAConditionNameOutsideAStoredProgram(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramReference->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SIGNAL not_found');
    }

    public function testAssignmentsKeepRequestOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("RESIGNAL SET SCHEMA_NAME = 'a', CATALOG_NAME = 'b'");
        self::assertInstanceOf(ResignalStatement::class, $statement);
        self::assertSame(['SCHEMA_NAME', 'CATALOG_NAME'], array_map(static fn ($assignment): string => $assignment->item->value, $statement->assignments));
    }

    #[TestWith(["SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = local_text", 'mysql-8.4.7', 'stored-program-reference'])]
    #[TestWith(["SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @a := 'x'", 'mysql-5.6.51', 'signal-information'])]
    public function testValueRejectsReferencesAndAssignments(string $sql, string $version, string $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::from($violation)->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
    }
}
