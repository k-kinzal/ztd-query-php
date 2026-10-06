<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\ConditionDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(GetDiagnostics::class)]
#[Medium]
final class GetDiagnosticsTest extends TestCase
{
    public function testDeriveStatementReadsStatementInformationIntoUserVariables(): void
    {
        $get = (new Semantics(Dialect::MySql))->analyze('GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT');
        $statement = $get->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);

        self::assertNull($statement->area);
        self::assertInstanceOf(StatementDiagnostics::class, $statement->information);
        self::assertCount(2, $statement->information->items);
        self::assertSame([], $get->facts->diagnostics);
    }

    public function testDeriveStatementReadsConditionInformationOfAVariableNumber(): void
    {
        $get = (new Semantics(Dialect::MySql))->analyze('GET CURRENT DIAGNOSTICS CONDITION @i @m = MESSAGE_TEXT, @e = MYSQL_ERRNO, @s = RETURNED_SQLSTATE');
        $statement = $get->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);

        self::assertSame(DiagnosticsArea::Current, $statement->area);
        self::assertInstanceOf(ConditionDiagnostics::class, $statement->information);
        self::assertCount(3, $statement->information->items);
        self::assertSame([], $get->facts->diagnostics);
    }

    public function testDeriveStatementReportsANameTargetOutsideAProgram(): void
    {
        $get = (new Semantics(Dialect::MySql))->analyze('GET DIAGNOSTICS n = NUMBER');
        $problem = $get->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $get->facts->diagnostics);
        self::assertSame(ProgramRule::UndeclaredVariable, $problem->rule);
        self::assertSame('Undeclared variable: n', $problem->message());
    }

    public function testDeriveStatementDerivesTheConditionNumber(): void
    {
        $get = (new Semantics(Dialect::MySql))->analyze('GET DIAGNOSTICS CONDITION k @m = MESSAGE_TEXT');

        self::assertSame(['Column k does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $get->facts->diagnostics));
    }

    public function testDeriveStatementRefusesTheStackedAreaBeforeMySql57(): void
    {
        $this->expectExceptionMessage("Unexpected 'STACKED' at line 1, column 5, expected DIAGNOSTICS_SYM");

        (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('GET STACKED DIAGNOSTICS CONDITION 1 @m = MESSAGE_TEXT');
    }

    public function testDeriveProgramResolvesParametersAndLocalVariables(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(i INT) BEGIN DECLARE m TEXT; DECLARE EXIT HANDLER FOR SQLEXCEPTION GET STACKED DIAGNOSTICS CONDITION i m = MESSAGE_TEXT, @e = MYSQL_ERRNO; END');
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $handler = $body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        $get = $handler->statement;
        self::assertInstanceOf(GetDiagnostics::class, $get);

        self::assertSame(DiagnosticsArea::Stacked, $get->area);
        self::assertInstanceOf(ConditionDiagnostics::class, $get->information);
        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsAnUndeclaredTarget(): iterable
    {
        yield 'statement information' => [
            'CREATE PROCEDURE p() BEGIN DECLARE n INT; GET DIAGNOSTICS n = NUMBER, x = ROW_COUNT; END',
            ['Undeclared variable: x'],
        ];
        yield 'condition information' => [
            'CREATE PROCEDURE p() BEGIN DECLARE m TEXT; GET DIAGNOSTICS CONDITION 1 m = MESSAGE_TEXT, e = MYSQL_ERRNO; END',
            ['Undeclared variable: e'],
        ];
        yield 'variable of a closed block' => [
            'CREATE PROCEDURE p() BEGIN BEGIN DECLARE n INT; END; GET DIAGNOSTICS n = NUMBER; END',
            ['Undeclared variable: n'],
        ];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsAnUndeclaredTarget')]
    public function testDeriveProgramReportsAnUndeclaredTarget(string $sql, array $messages): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheStatement(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51', 'get current diagnostics @n = number', 'GET CURRENT DIAGNOSTICS @n = NUMBER'];
        yield 'mysql 5.7' => ['mysql-5.7.44', 'get stacked diagnostics condition 1 @m = message_text', 'GET STACKED DIAGNOSTICS CONDITION 1 @m = MESSAGE_TEXT'];
        yield 'mysql 8.0' => ['mysql-8.0.44', 'GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT', 'GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT'];
        yield 'mysql 8.4' => ['mysql-8.4.7', 'GET DIAGNOSTICS CONDITION @c @t = TABLE_NAME, @s = RETURNED_SQLSTATE', 'GET DIAGNOSTICS CONDITION @c @t = TABLE_NAME, @s = RETURNED_SQLSTATE'];
        yield 'mysql 9.1' => ['mysql-9.1.0', 'GET STACKED DIAGNOSTICS CONDITION 2 @o = CLASS_ORIGIN', 'GET STACKED DIAGNOSTICS CONDITION 2 @o = CLASS_ORIGIN'];
    }

    #[DataProvider('providerRenderWritesTheStatement')]
    public function testRenderWritesTheStatement(string $release, string $sql, string $expected): void
    {
        $get = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertInstanceOf(GetDiagnostics::class, $get->statement);
        self::assertSame($expected, $get->toString());
    }
}
