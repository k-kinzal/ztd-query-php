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
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\ConditionRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ConditionRule::class)]
#[Medium]
final class ConditionRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerValueLowersAnErrorCodeAndAnSqlstate(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerValueLowersAnErrorCodeAndAnSqlstate')]
    public function testValueLowersAnErrorCodeAndAnSqlstate(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("create procedure p() begin declare c condition for 1051; declare d condition for sqlstate '42S02'; declare e condition for sqlstate value '42S01'; end");
        $statement = $operation;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);
        self::assertEquals([
            new ConditionDeclaration(new Name('c'), new ErrorCode(new Numeral('1051'))),
            new ConditionDeclaration(new Name('d'), new SqlState(new Text('42S02'))),
            new ConditionDeclaration(new Name('e'), new SqlState(new Text('42S01'))),
        ], $statement->body->declarations);
        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE d CONDITION FOR SQLSTATE '42S02'; DECLARE e CONDITION FOR SQLSTATE '42S01'; END", $operation->toString());
    }

    public function testValueLowersTheSqlstateOfResignal(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("resignal sqlstate value '45000'");

        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference(new Resignal(new SqlState(new Text('45000'))), $operation));
        self::assertSame("RESIGNAL SQLSTATE '45000'", $operation->toString());
    }

    public function testValueRefusesANodeOfNoProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new ConditionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('The grammar release has no production rule#0.');

        $rule->value(new Node('rule', 0, []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerHandledLowersEveryKindOfCondition(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerHandledLowersEveryKindOfCondition')]
    public function testHandledLowersEveryKindOfCondition(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("create procedure p() begin declare c condition for 1051; declare continue handler for c, 1052, sqlstate value '42000', sqlwarning, not found, sqlexception set @x = 1; end");
        $statement = $operation;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);
        $handler = $statement->body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);
        self::assertSame(HandlerAction::Continue, $handler->action);
        self::assertEquals([
            new ConditionName(new Name('c')),
            new ErrorCode(new Numeral('1052')),
            new SqlState(new Text('42000')),
            new GeneralCondition(ConditionClass::SqlWarning),
            new GeneralCondition(ConditionClass::NotFound),
            new GeneralCondition(ConditionClass::SqlException),
        ], $handler->conditions);
        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR c, 1052, SQLSTATE '42000', SQLWARNING, NOT FOUND, SQLEXCEPTION SET @x = 1; END", $operation->toString());
        self::assertSame([], $operation->diagnostics());
    }

    public function testHandledLowersASingleCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('create procedure p() begin declare exit handler for sqlexception begin end; end');
        $statement = $operation;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference([new HandlerDeclaration(HandlerAction::Exit, [new GeneralCondition(ConditionClass::SqlException)], new Block())], $statement->body->declarations));
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN END; END', $operation->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRejectedSqlStates(): iterable
    {
        $inputs = [
            ["SIGNAL SQLSTATE '00000'", '00000'],
            ["SIGNAL SQLSTATE '00123'", '00123'],
            ["SIGNAL SQLSTATE '4500'", '4500'],
            ["SIGNAL SQLSTATE '4500a'", '4500a'],
            ["RESIGNAL SQLSTATE '00000'", '00000'],
            ["RESIGNAL SQLSTATE '450001'", '450001'],
            ["CREATE PROCEDURE p() BEGIN SIGNAL SQLSTATE '00123'; END", '00123'],
            ["CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION RESIGNAL SQLSTATE '00000'; END", '00000'],
            ["CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '00000' BEGIN END; END", '00000'],
            ["CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '00123'; END", '00123'],
            ["CREATE PROCEDURE p() SIGNAL SQLSTATE '4500'", '4500'],
            ["CREATE PROCEDURE p() SIGNAL SQLSTATE VALUE '450000'", '450000'],
            ["CREATE PROCEDURE p() SIGNAL SQLSTATE '4500a'", '4500a'],
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ($inputs as $index => [$sql, $value]) {
                yield $release . ':' . $index => [$release, $sql, $value];
            }
        }
    }

    #[DataProvider('providerRejectedSqlStates')]
    public function testValueRejectsAnInvalidOperandBeforePublishingAStructure(string $release, string $sql, string $value): void
    {
        $failure = null;
        try {
            (new Semantics(Dialect::MySql, 'mysql-' . $release))->analyze($sql);
        } catch (\SqlSemantics\Construction\RejectedInput $caught) {
            $failure = $caught;
        }

        self::assertInstanceOf(\SqlSemantics\Construction\RejectedInput::class, $failure);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::BadSqlState, $value), $failure->problem);
        $offset = strpos($sql, "'" . $value . "'");
        self::assertNotFalse($offset);
        self::assertSame($offset + strlen($value) + 2, $failure->end);
    }
}
