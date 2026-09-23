<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\ProcessInfoColumn;
use SqlSemantics\Model\Query\Inspection\ProcessQueryText;
use SqlSemantics\Model\Statement\Inspection\ShowProcessesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\StringStorage;

#[CoversClass(ShowProcessesStatement::class)]
#[Medium]
final class ShowProcessesStatementTest extends TestCase
{
    public function testResultColumnsDescribeConnectionActivityAndQueryText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCESSLIST');
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        self::assertSame(['Id', 'User', 'Host', 'db', 'Command', 'Time', 'State', 'Info'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('bigint', $statement->resultColumns()[0]->expression->type->name);
        self::assertSame('integer', $statement->resultColumns()[5]->expression->type->name);
        $info = $statement->resultColumns()[7]->expression;
        self::assertInstanceOf(ProcessInfoColumn::class, $info);
        self::assertSame(ProcessQueryText::Preview, $info->detail);
        self::assertInstanceOf(StringStorage::class, $info->type->identity);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericParameter::class, $info->type->identity->length);
        self::assertSame('100', $info->type->identity->length->spelling);
    }

    public function testWithQueryTextRequestsCompleteTextWithoutAStalePreviewLength(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW /* layout */ PROCESSLIST');
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        $changed = $statement->withQueryText(ProcessQueryText::Complete);
        self::assertSame(ProcessQueryText::Preview, $statement->queryText);
        self::assertSame(ProcessQueryText::Complete, $changed->queryText);
        self::assertSame('SHOW FULL PROCESSLIST', $changed->toString());
        $info = $changed->resultColumns()[7]->expression;
        self::assertInstanceOf(ProcessInfoColumn::class, $info);
        self::assertInstanceOf(StringStorage::class, $info->type->identity);
        self::assertNull($info->type->identity->length);
    }

    public function testWithOriginRetainsTheTextPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW FULL PROCESSLIST');
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->queryText, $copy->queryText);
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ShowProcessesStatement($origin);
    }

}
