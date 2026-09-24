<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\Diagnostics;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;
use SqlSemantics\Model\Configuration\Condition\DiagnosticsArea;
use SqlSemantics\Model\Statement\Procedural\GetConditionDiagnosticsStatement;
use SqlSemantics\Model\Statement\Procedural\GetDiagnosticsStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Diagnostics::class)]
#[Medium]
final class DiagnosticsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindSeparatesStatementAndConditionInformation(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('GET DIAGNOSTICS @n = NUMBER');
        self::assertInstanceOf(GetDiagnosticsStatement::class, $statement);
        self::assertSame(DiagnosticsArea::Current, $statement->area);
        $condition = $binder->bind("GET CURRENT DIAGNOSTICS CONDITION @k @'m' = MESSAGE_TEXT, @s = RETURNED_SQLSTATE", strict: false);
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $condition);
        self::assertSame(DiagnosticsArea::Current, $condition->area);
        self::assertSame(['m', 's'], array_column($condition->items, 'variable'));
        self::assertSame([ConditionItem::MessageText, ConditionItem::ReturnedSqlState], array_column($condition->items, 'item'));
        self::assertSame($condition->toString(), $binder->bind($condition->toString(), strict: false)->toString());
    }

    public function testTargetRejectsALocalVariableOutsideAStoredProgram(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramReference->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS local_count = NUMBER');
    }

    public function testItemReadsTheTrailingKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS @r = row_count');
        self::assertInstanceOf(GetDiagnosticsStatement::class, $statement);
        self::assertSame('ROW_COUNT', Diagnostics::item(\SqlSemantics\Ast\Tree::outer($statement->source, ['statement_information_item'])[0]));
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsLowercaseDiagnosticsAreas')]
    public function testBindReadsLowercaseDiagnosticsAreas(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsLowercaseDiagnosticsAreas(): iterable
    {
        return [
            'get diagnostics @a = number (MySql)' => [Dialect::MySql, null, [], 'get diagnostics @a = number', 'GET CURRENT DIAGNOSTICS @`a` = NUMBER'],
            'get current diagnostics @a = row_count (MySql)' => [Dialect::MySql, null, [], 'get current diagnostics @a = row_count', 'GET CURRENT DIAGNOSTICS @`a` = ROW_COUNT'],
            'get stacked diagnostics condition 1 @a = message_text (MySql)' => [Dialect::MySql, null, [], 'get stacked diagnostics condition 1 @a = message_text', 'GET STACKED DIAGNOSTICS CONDITION 1 @`a` = MESSAGE_TEXT'],
            'GET DIAGNOSTICS CONDITION 1 @a = RETURNED_SQLSTATE, @b = MYSQL_ERRNO (MySql)' => [Dialect::MySql, null, [], 'GET DIAGNOSTICS CONDITION 1 @a = RETURNED_SQLSTATE, @b = MYSQL_ERRNO', 'GET CURRENT DIAGNOSTICS CONDITION 1 @`a` = RETURNED_SQLSTATE, @`b` = MYSQL_ERRNO'],
        ];
    }
}
