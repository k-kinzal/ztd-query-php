<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\DiagnosticsRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\ConditionDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DiagnosticsRule::class)]
#[Medium]
final class DiagnosticsRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatementLowersStatementInformation(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerStatementLowersStatementInformation')]
    public function testStatementLowersStatementInformation(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('get diagnostics @n = number, @r = row_count');

        self::assertEquals(new GetDiagnostics(new StatementDiagnostics([
            new InformationItem(new UserVariable(new Name('n')), StatementItemName::Number),
            new InformationItem(new UserVariable(new Name('r')), StatementItemName::RowCount),
        ])), $operation->statement);
        self::assertSame('GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT', $operation->toString());
    }

    /**
     * @return iterable<string, array{string, string, DiagnosticsArea|null, string}>
     */
    public static function providerStatementLowersTheArea(): iterable
    {
        yield 'current in 5.6' => ['mysql-5.6.51', 'get current diagnostics @n = number', DiagnosticsArea::Current, 'GET CURRENT DIAGNOSTICS @n = NUMBER'];
        yield 'stacked in 5.7' => ['mysql-5.7.44', 'get stacked diagnostics @n = number', DiagnosticsArea::Stacked, 'GET STACKED DIAGNOSTICS @n = NUMBER'];
        yield 'stacked in 8.0' => ['mysql-8.0.44', 'get stacked diagnostics @n = number', DiagnosticsArea::Stacked, 'GET STACKED DIAGNOSTICS @n = NUMBER'];
        yield 'none in 9.1' => ['mysql-9.1.0', 'get diagnostics @n = number', null, 'GET DIAGNOSTICS @n = NUMBER'];
    }

    #[DataProvider('providerStatementLowersTheArea')]
    public function testStatementLowersTheArea(string $release, string $sql, ?DiagnosticsArea $area, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $operation->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);

        self::assertSame($area, $statement->area);
        self::assertSame($rendering, $operation->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatementLowersConditionInformation(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerStatementLowersConditionInformation')]
    public function testStatementLowersConditionInformation(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('get current diagnostics condition 1 @a = class_origin, @b = returned_sqlstate');

        self::assertEquals(new GetDiagnostics(new ConditionDiagnostics(new NumberLiteral('1'), [
            new InformationItem(new UserVariable(new Name('a')), ConditionItemName::ClassOrigin),
            new InformationItem(new UserVariable(new Name('b')), ConditionItemName::ReturnedSqlstate),
        ]), DiagnosticsArea::Current), $operation->statement);
        self::assertSame('GET CURRENT DIAGNOSTICS CONDITION 1 @a = CLASS_ORIGIN, @b = RETURNED_SQLSTATE', $operation->toString());
    }

    public function testStatementLowersTheConditionNumberAsAVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('get diagnostics condition @c @m = message_text');
        $statement = $operation->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);
        self::assertInstanceOf(ConditionDiagnostics::class, $statement->information);

        self::assertEquals(new UserVariable(new Name('c')), $statement->information->number);
        self::assertSame('GET DIAGNOSTICS CONDITION @c @m = MESSAGE_TEXT', $operation->toString());
    }

    public function testStatementRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new DiagnosticsRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: rule: GET x');

        $rule->statement(new Form(new Node('rule', 0, []), 'rule: GET x'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerItemsLowersEveryConditionItemName(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerItemsLowersEveryConditionItemName')]
    public function testItemsLowersEveryConditionItemName(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('get diagnostics condition 1 @a = class_origin, @b = subclass_origin, @c = constraint_catalog, @d = constraint_schema, @e = constraint_name, @f = catalog_name, @g = schema_name, @h = table_name, @i = column_name, @j = cursor_name, @k = message_text, @l = mysql_errno, @m = returned_sqlstate');
        $statement = $operation->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);

        self::assertSame(ConditionItemName::cases(), array_map(static fn (InformationItem $item): ConditionItemName|StatementItemName => $item->item, $statement->information->items));
        self::assertSame('GET DIAGNOSTICS CONDITION 1 @a = CLASS_ORIGIN, @b = SUBCLASS_ORIGIN, @c = CONSTRAINT_CATALOG, @d = CONSTRAINT_SCHEMA, @e = CONSTRAINT_NAME, @f = CATALOG_NAME, @g = SCHEMA_NAME, @h = TABLE_NAME, @i = COLUMN_NAME, @j = CURSOR_NAME, @k = MESSAGE_TEXT, @l = MYSQL_ERRNO, @m = RETURNED_SQLSTATE', $operation->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerItemsLowersLocalVariableTargets(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerItemsLowersLocalVariableTargets')]
    public function testItemsLowersLocalVariableTargets(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() begin declare n, t int; get diagnostics n = number; get diagnostics condition n t = mysql_errno; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertEquals([
            new GetDiagnostics(new StatementDiagnostics([new InformationItem(new Name('n'), StatementItemName::Number)])),
            new GetDiagnostics(new ConditionDiagnostics(new ColumnUse(new Name('n')), [new InformationItem(new Name('t'), ConditionItemName::MysqlErrno)])),
        ], $statement->body->statements);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE n, t INT; GET DIAGNOSTICS n = NUMBER; GET DIAGNOSTICS CONDITION n t = MYSQL_ERRNO; END', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}
