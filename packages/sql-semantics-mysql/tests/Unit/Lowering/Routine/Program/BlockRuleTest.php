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
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\BlockRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CloseCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(BlockRule::class)]
#[Medium]
final class BlockRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBlockLowersAnUnlabeledBlock(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerBlockLowersAnUnlabeledBlock')]
    public function testBlockLowersAnUnlabeledBlock(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $empty = $semantics->analyze('create procedure p() begin end');
        self::assertInstanceOf(CreateProcedure::class, $empty->statement);
        self::assertEquals(new Block(), $empty->statement->body);
        self::assertSame('CREATE PROCEDURE p() BEGIN END', $empty->toString());

        $full = $semantics->analyze('create procedure p() begin declare x int; set x = 1; set x = 2; end');
        self::assertInstanceOf(CreateProcedure::class, $full->statement);
        self::assertEquals(new Block(
            [new VariableDeclaration([new Name('x')], new Integral(IntegralKind::Int))],
            [new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('1'))]), new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('2'))])],
        ), $full->statement->body);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE x INT; SET x = 1; SET x = 2; END', $full->toString());
    }

    /**
     * @return iterable<string, array{string, string, Name|null, string}>
     */
    public static function providerBlockLowersALabeledBlock(): iterable
    {
        yield 'with the end label in 5.6' => ['mysql-5.6.51', 'create procedure p() b: begin leave b; end b', new Name('b'), 'CREATE PROCEDURE p() b: BEGIN LEAVE b; END b'];
        yield 'without the end label in 5.7' => ['mysql-5.7.44', 'create procedure p() b: begin leave b; end', null, 'CREATE PROCEDURE p() b: BEGIN LEAVE b; END'];
        yield 'with the end label in 8.0' => ['mysql-8.0.44', 'create procedure p() b: begin leave b; end b', new Name('b'), 'CREATE PROCEDURE p() b: BEGIN LEAVE b; END b'];
        yield 'without the end label in 9.1' => ['mysql-9.1.0', 'create procedure p() b: begin leave b; end', null, 'CREATE PROCEDURE p() b: BEGIN LEAVE b; END'];
    }

    #[DataProvider('providerBlockLowersALabeledBlock')]
    public function testBlockLowersALabeledBlock(string $release, string $sql, ?Name $endLabel, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);

        self::assertEquals(new Block([], [new Leave(new Name('b'))], new Name('b'), $endLabel), $operation->statement->body);
        self::assertSame($rendering, $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testBlockRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new BlockRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: rule: BEGIN x');

        $rule->block(new Form(new Node('rule', 0, []), 'rule: BEGIN x'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeclarationLowersAVariableWithItsCollationAndDefault(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerDeclarationLowersAVariableWithItsCollationAndDefault')]
    public function testDeclarationLowersAVariableWithItsCollationAndDefault(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("create procedure p() begin declare s varchar(5) collate utf8mb4_bin default 'a'; end");
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertEquals([new VariableDeclaration([new Name('s')], new Character(CharacterKind::VarChar, '5'), new CollationName(new Name('utf8mb4_bin')), new StringLiteral(['a']))], $statement->body->declarations);
        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE s VARCHAR(5) COLLATE utf8mb4_bin DEFAULT 'a'; END", $operation->toString());
    }

    /**
     * @return iterable<string, array{string, string, class-string, string}>
     */
    public static function providerDeclarationLowersACursor(): iterable
    {
        yield 'a select in 5.6' => ['mysql-5.6.51', 'select 1', Select::class, 'SELECT 1'];
        yield 'a union in 5.7' => ['mysql-5.7.44', 'select 1 union select 2', SetOperation::class, 'SELECT 1 UNION SELECT 2'];
        yield 'a select in 8.0' => ['mysql-8.0.44', 'select 1', Select::class, 'SELECT 1'];
        yield 'a union in 9.1' => ['mysql-9.1.0', 'select 1 union select 2', SetOperation::class, 'SELECT 1 UNION SELECT 2'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('providerDeclarationLowersACursor')]
    public function testDeclarationLowersACursor(string $release, string $query, string $class, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() begin declare c cursor for ' . $query . '; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);
        $declaration = $statement->body->declarations[0];
        self::assertInstanceOf(CursorDeclaration::class, $declaration);

        self::assertSame('c', $declaration->name->value);
        self::assertInstanceOf($class, $declaration->query);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR ' . $rendering . '; END', $operation->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeclarationLowersAConditionAndAHandler(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerDeclarationLowersAConditionAndAHandler')]
    public function testDeclarationLowersAConditionAndAHandler(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("create procedure p() begin declare x int; declare e condition for sqlstate '22012'; declare exit handler for not found set x = 0; declare continue handler for sqlwarning begin end; end");
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertEquals([
            new VariableDeclaration([new Name('x')], new Integral(IntegralKind::Int)),
            new ConditionDeclaration(new Name('e'), new SqlState(new Text('22012'))),
            new HandlerDeclaration(HandlerAction::Exit, [new GeneralCondition(ConditionClass::NotFound)], new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('0'))])),
            new HandlerDeclaration(HandlerAction::Continue, [new GeneralCondition(ConditionClass::SqlWarning)], new Block()),
        ], $statement->body->declarations);
        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE e CONDITION FOR SQLSTATE '22012'; DECLARE EXIT HANDLER FOR NOT FOUND SET x = 0; DECLARE CONTINUE HANDLER FOR SQLWARNING BEGIN END; END", $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }


    /**
     * @return iterable<string, array{string}>
     */
    public static function providerNamesLowersTheDeclaredAndTheFetchedNames(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerNamesLowersTheDeclaredAndTheFetchedNames')]
    public function testNamesLowersTheDeclaredAndTheFetchedNames(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() begin declare a, b, c int; declare k cursor for select 1, 2, 3; fetch k into a, b, c; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);
        $variables = $statement->body->declarations[0];
        self::assertInstanceOf(VariableDeclaration::class, $variables);

        self::assertEquals([new Name('a'), new Name('b'), new Name('c')], $variables->names);
        self::assertEquals([new FetchCursor(new Name('k'), [new Name('a'), new Name('b'), new Name('c')])], $statement->body->statements);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE a, b, c INT; DECLARE k CURSOR FOR SELECT 1, 2, 3; FETCH k INTO a, b, c; END', $operation->toString());
    }

    /**
     * @return iterable<string, array{string, string, Arithmetic|null, string}>
     */
    public static function providerInitialLowersTheDefaultClause(): iterable
    {
        yield 'absent in 5.6' => ['mysql-5.6.51', 'declare x int', null, 'DECLARE x INT'];
        yield 'an expression in 5.7' => ['mysql-5.7.44', 'declare x int default 1 + 2', new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), 'DECLARE x INT DEFAULT 1 + 2'];
        yield 'absent in 8.0' => ['mysql-8.0.44', 'declare x int', null, 'DECLARE x INT'];
        yield 'an expression in 9.1' => ['mysql-9.1.0', 'declare x int default 1 + 2', new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), 'DECLARE x INT DEFAULT 1 + 2'];
    }

    #[DataProvider('providerInitialLowersTheDefaultClause')]
    public function testInitialLowersTheDefaultClause(string $release, string $declaration, ?Arithmetic $default, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() begin ' . $declaration . '; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertEquals([new VariableDeclaration([new Name('x')], new Integral(IntegralKind::Int), null, $default)], $statement->body->declarations);
        self::assertSame('CREATE PROCEDURE p() BEGIN ' . $rendering . '; END', $operation->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCursorLowersOpenFetchAndClose(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerCursorLowersOpenFetchAndClose')]
    public function testCursorLowersOpenFetchAndClose(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() begin declare a int; declare k cursor for select 1; open k; fetch next from k into a; fetch from k into a; fetch k into a; close k; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertEquals([
            new OpenCursor(new Name('k')),
            new FetchCursor(new Name('k'), [new Name('a')]),
            new FetchCursor(new Name('k'), [new Name('a')]),
            new FetchCursor(new Name('k'), [new Name('a')]),
            new CloseCursor(new Name('k')),
        ], $statement->body->statements);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE a INT; DECLARE k CURSOR FOR SELECT 1; OPEN k; FETCH k INTO a; FETCH k INTO a; FETCH k INTO a; CLOSE k; END', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}
