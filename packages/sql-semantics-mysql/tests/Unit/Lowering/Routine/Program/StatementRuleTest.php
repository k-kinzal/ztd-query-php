<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\StatementRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CloseCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Node as StatementNode;

#[CoversClass(StatementRule::class)]
#[Medium]
final class StatementRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatementRoutesEveryKindOfProgramStatement(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerStatementRoutesEveryKindOfProgramStatement')]
    public function testStatementRoutesEveryKindOfProgramStatement(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create function f(x int) returns int begin declare a int; declare k cursor for select 1; set a = 1; if x then set a = 1; end if; case x when 1 then set a = 2; end case; b: begin leave b; end b; begin end; l: loop iterate l; end loop; while 0 do set a = 0; end while; open k; fetch k into a; close k; return a; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertSame([
            SetVariables::class, IfStatement::class, SimpleCase::class, Block::class, Block::class, Loop::class, WhileLoop::class,
            OpenCursor::class, FetchCursor::class, CloseCursor::class, ReturnStatement::class,
        ], array_map(static fn (StatementNode $node): string => $node::class, $statement->body->statements));
        self::assertSame('CREATE FUNCTION f(x INT) RETURNS INT BEGIN DECLARE a INT; DECLARE k CURSOR FOR SELECT 1; SET a = 1; IF x THEN SET a = 1; END IF; CASE x WHEN 1 THEN SET a = 2; END CASE; b: BEGIN LEAVE b; END b; BEGIN END; l: LOOP ITERATE l; END LOOP; WHILE 0 DO SET a = 0; END WHILE; OPEN k; FETCH k INTO a; CLOSE k; RETURN a; END', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testStatementRefusesANodeOfNoProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new StatementRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('The grammar release has no production rule#0.');

        $rule->statement(new Node('rule', 0, []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatementsLowersTheListInOrder(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerStatementsLowersTheListInOrder')]
    public function testStatementsLowersTheListInOrder(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('create trigger tr before insert on t for each row begin set @a = 1; select 2 into @b; set new.a = 3; end', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertSame([SetVariables::class, Select::class, SetVariables::class], array_map(static fn (StatementNode $node): string => $node::class, $statement->body->statements));
        self::assertSame('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN SET @a = 1; SELECT 2 INTO @b; SET `new`.a = 3; END', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, class-string, string}>
     */
    public static function providerEventLowersTheStatementOfAnEvent(): iterable
    {
        yield 'an SQL statement in 5.6' => ['mysql-5.6.51', 'select 1', Select::class, 'SELECT 1'];
        yield 'a block in 5.7' => ['mysql-5.7.44', 'begin set @a = 1; end', Block::class, 'BEGIN SET @a = 1; END'];
        yield 'an IF in 8.0' => ['mysql-8.0.44', 'if 1 then set @a = 1; end if', IfStatement::class, 'IF 1 THEN SET @a = 1; END IF'];
        yield 'a CASE in 8.4' => ['mysql-8.4.7', 'case when 1 then set @a = 1; end case', SearchedCase::class, 'CASE WHEN 1 THEN SET @a = 1; END CASE'];
        yield 'a labeled loop in 9.0' => ['mysql-9.0.1', 'l: loop leave l; end loop', Loop::class, 'l: LOOP LEAVE l; END LOOP'];
        yield 'an unlabeled loop in 9.1' => ['mysql-9.1.0', 'while 0 do set @a = 1; end while', WhileLoop::class, 'WHILE 0 DO SET @a = 1; END WHILE'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('providerEventLowersTheStatementOfAnEvent')]
    public function testEventLowersTheStatementOfAnEvent(string $release, string $sql, string $class, string $rendering): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $create = $semantics->analyze('create event e on schedule every 1 day do ' . $sql);
        self::assertInstanceOf(CreateEvent::class, $create->statement);
        self::assertInstanceOf($class, $create->statement->body);
        self::assertSame('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO ' . $rendering, $create->toString());

        $alter = $semantics->analyze('alter event e do ' . $sql);
        self::assertInstanceOf(AlterEvent::class, $alter->statement);
        self::assertInstanceOf($class, $alter->statement->body);
        self::assertSame('ALTER EVENT e DO ' . $rendering, $alter->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBodyLowersAProgramStatement(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 8.1' => ['mysql-8.1.0'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerBodyLowersAProgramStatement')]
    public function testBodyLowersAProgramStatement(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $procedure = $semantics->analyze('create procedure p() begin end');
        self::assertInstanceOf(CreateProcedure::class, $procedure->statement);
        self::assertEquals(new Block(), $procedure->statement->body);
        self::assertSame('CREATE PROCEDURE p() BEGIN END', $procedure->toString());

        $function = $semantics->analyze('create function f() returns int return 1');
        self::assertInstanceOf(CreateFunction::class, $function->statement);
        self::assertInstanceOf(ReturnStatement::class, $function->statement->body);
        self::assertSame('CREATE FUNCTION f() RETURNS INT RETURN 1', $function->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBodyLowersAnExternalBody(): iterable
    {
        yield 'mysql 8.1' => ['mysql-8.1.0'];
        yield 'mysql 8.4' => ['mysql-8.4.7'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerBodyLowersAnExternalBody')]
    public function testBodyLowersAnExternalBody(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $function = $semantics->analyze("create function f() returns int language javascript as 'return 1'");
        self::assertInstanceOf(CreateFunction::class, $function->statement);
        self::assertEquals(new ExternalBody(new Text('return 1')), $function->statement->body);
        self::assertSame("CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS 'return 1'", $function->toString());

        $procedure = $semantics->analyze('create procedure p() language javascript as $$ return 1 $$');
        self::assertInstanceOf(CreateProcedure::class, $procedure->statement);
        self::assertEquals(new ExternalBody(new DollarQuotedText(' return 1 ')), $procedure->statement->body);
        self::assertSame('CREATE PROCEDURE p() LANGUAGE javascript AS $$ return 1 $$', $procedure->toString());
    }

    /**
     * @return iterable<string, array{string, DollarQuotedText, string}>
     */
    public static function providerDollarQuotedSplitsTheTagAndTheText(): iterable
    {
        yield 'without a tag' => ['$$return 1$$', new DollarQuotedText('return 1'), '$$return 1$$'];
        yield 'an empty text' => ['$$$$', new DollarQuotedText(''), '$$$$'];
        yield 'with a tag' => ['$js$ return "$x$" $js$', new DollarQuotedText(' return "$x$" ', 'js'), '$js$ return "$x$" $js$'];
    }

    #[DataProvider('providerDollarQuotedSplitsTheTagAndTheText')]
    public function testDollarQuotedSplitsTheTagAndTheText(string $string, DollarQuotedText $expected, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('create procedure p() language javascript as ' . $string);
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);

        self::assertEquals(new ExternalBody($expected), $operation->statement->body);
        self::assertSame('CREATE PROCEDURE p() LANGUAGE javascript AS ' . $rendering, $operation->toString());
    }
}
