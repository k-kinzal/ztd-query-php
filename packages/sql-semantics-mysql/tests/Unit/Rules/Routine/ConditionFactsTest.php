<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\ConditionFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ConditionFacts::class)]
#[Medium]
final class ConditionFactsTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerValueChecksTheFormOfAnSqlState')]
    public function testValueChecksTheFormOfAnSqlState(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerValueChecksTheFormOfAnSqlState(): iterable
    {
        yield 'success in a handler' => ['mysql-9.1.0', "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '00000' BEGIN END; END", ["Bad SQLSTATE: '00000'"]];
        yield 'success in a condition in 5.6' => ['mysql-5.6.51', "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '00123'; END", ["Bad SQLSTATE: '00123'"]];
        yield 'four characters in SIGNAL' => ['mysql-8.0.44', "CREATE PROCEDURE p() SIGNAL SQLSTATE '4500'", ["Bad SQLSTATE: '4500'"]];
        yield 'six characters in SIGNAL' => ['mysql-9.1.0', "CREATE PROCEDURE p() SIGNAL SQLSTATE VALUE '450000'", ["Bad SQLSTATE: '450000'"]];
        yield 'a lower-case letter in SIGNAL' => ['mysql-9.1.0', "CREATE PROCEDURE p() SIGNAL SQLSTATE '4500a'", ["Bad SQLSTATE: '4500a'"]];
        yield 'a warning in SIGNAL' => ['mysql-9.1.0', "CREATE PROCEDURE p() SIGNAL SQLSTATE '01000'", []];
        yield 'an upper-case letter in a handler' => ['mysql-5.7.44', "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '42S02', NOT FOUND, SQLWARNING BEGIN END; END", []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerValueReportsAConditionNameDeclaredNowhereAround')]
    public function testValueReportsAConditionNameDeclaredNowhereAround(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerValueReportsAConditionNameDeclaredNowhereAround(): iterable
    {
        yield 'an undeclared handler condition' => ['CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR nope BEGIN END; END', ['Undefined CONDITION: nope']];
        yield 'an undeclared SIGNAL condition' => ['CREATE PROCEDURE p() SIGNAL c', ['Undefined CONDITION: c']];
        yield 'a condition of an inner block' => ["CREATE PROCEDURE p() BEGIN BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; END; SIGNAL c; END", ['Undefined CONDITION: c']];
        yield 'a condition of an outer block' => ["CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN SIGNAL C; END; END", []];
        yield 'a condition in another case' => ['CREATE PROCEDURE p() BEGIN DECLARE E CONDITION FOR 1051; DECLARE EXIT HANDLER FOR e BEGIN END; END', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerSignalReportsTheBrokenRule')]
    public function testSignalReportsTheBrokenRule(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSignalReportsTheBrokenRule(): iterable
    {
        yield 'SIGNAL of an error code condition' => ['CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; SIGNAL c; END', ['SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE']];
        yield 'RESIGNAL of an error code condition' => ['CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; RESIGNAL c; END', ['SIGNAL/RESIGNAL can only use a CONDITION defined with SQLSTATE']];
        yield 'SIGNAL of an SQLSTATE condition' => ["CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c SET MESSAGE_TEXT = 'm'; END", []];
        yield 'an item set twice by SIGNAL' => ["CREATE PROCEDURE p() SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a', MYSQL_ERRNO = 1, MESSAGE_TEXT = 'b'", ["Duplicate condition information item 'MESSAGE_TEXT'"]];
        yield 'an item set twice by RESIGNAL' => ["CREATE PROCEDURE p() RESIGNAL SET CLASS_ORIGIN = 'a', CLASS_ORIGIN = 'b'", ["Duplicate condition information item 'CLASS_ORIGIN'"]];
        yield 'RESIGNAL without a condition' => ['CREATE PROCEDURE p() RESIGNAL', []];
        yield 'an unknown name in an item' => ["CREATE PROCEDURE p() SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = z", ['Column z does not exist.']];
    }

    public function testSignalResolvesTheValueOfAnItemToAParameter(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p(z TEXT) SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = z, MYSQL_ERRNO = 1");
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $signal = $create->body;
        self::assertInstanceOf(Signal::class, $signal);
        $resolution = $operation->facts->scalar($signal->items[0]->value)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame('z', $resolution->slot->name?->value);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerTargetsReportsATargetThatIsNoVariable')]
    public function testTargetsReportsATargetThatIsNoVariable(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerTargetsReportsATargetThatIsNoVariable(): iterable
    {
        yield 'a user variable and an undeclared name' => ['CREATE PROCEDURE p() GET DIAGNOSTICS @n = NUMBER, y = ROW_COUNT', ['Undeclared variable: y']];
        yield 'condition items' => ['CREATE PROCEDURE p() BEGIN DECLARE y INT; GET DIAGNOSTICS CONDITION 1 y = MESSAGE_TEXT, @m = RETURNED_SQLSTATE, z = CLASS_ORIGIN; END', ['Undeclared variable: z']];
        yield 'a variable of a closed block' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; BEGIN BEGIN DECLARE y INT; END; GET DIAGNOSTICS x = NUMBER, y = ROW_COUNT; END; END', ['Undeclared variable: y']];
        yield 'a parameter in another case' => ['CREATE PROCEDURE p(OUT n INT) GET DIAGNOSTICS N = NUMBER', []];
        yield 'variables of a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE m TEXT; DECLARE EXIT HANDLER FOR SQLEXCEPTION GET STACKED DIAGNOSTICS CONDITION 1 m = MESSAGE_TEXT; END', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerValueReportsTheErrorCodeZero')]
    public function testValueReportsTheErrorCodeZero(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerValueReportsTheErrorCodeZero(): iterable
    {
        yield 'zero in a condition in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; END', ["Incorrect CONDITION value: '0'"]];
        yield 'zero in a handler in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 0 BEGIN END; END', ["Incorrect CONDITION value: '0'"]];
        yield 'a decimal below one in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0.7; END', ["Incorrect CONDITION value: '0'"]];
        yield 'a hexadecimal zero in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0x00; END', ["Incorrect CONDITION value: '0'"]];
        yield 'a floating number in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1e2; END', []];
    }

    public function testMeaningAnswersTheDeclaredValueOfAConditionName(): void
    {
        $scope = new ProgramScope(new Environment(new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')])), ProgramKind::Procedure, conditions: [new ConditionDeclaration(new Name('c'), new ErrorCode(new Numeral('1051')))]);
        $code = new ErrorCode(new Numeral('1052'));

        self::assertEquals(new ErrorCode(new Numeral('1051')), (new ConditionFacts())->meaning(new ConditionName(new Name('C')), $scope));
        self::assertNull((new ConditionFacts())->meaning(new ConditionName(new Name('d')), $scope));
        self::assertSame($code, (new ConditionFacts())->meaning($code, $scope));
    }

    #[DataProvider('providerSameComparesTheValues')]
    public function testSameComparesTheValues(Condition $left, Condition $right, bool $expected): void
    {
        self::assertSame($expected, (new ConditionFacts())->same($left, $right));
    }

    /**
     * @return iterable<string, array{Condition, Condition, bool}>
     */
    public static function providerSameComparesTheValues(): iterable
    {
        yield 'one general condition' => [new GeneralCondition(ConditionClass::SqlException), new GeneralCondition(ConditionClass::SqlException), true];
        yield 'two general conditions' => [new GeneralCondition(ConditionClass::SqlException), new GeneralCondition(ConditionClass::NotFound), false];
        yield 'one error number in two spellings' => [new ErrorCode(new Numeral('1051')), new ErrorCode(new Numeral('41B', true)), true];
        yield 'two error numbers' => [new ErrorCode(new Numeral('1051')), new ErrorCode(new Numeral('1052')), false];
        yield 'one SQLSTATE value' => [new SqlState(new Text('42S02')), new SqlState(new Text('42S02')), true];
        yield 'two SQLSTATE values' => [new SqlState(new Text('42S02')), new SqlState(new Text('42S01')), false];
        yield 'an error number and an SQLSTATE value' => [new ErrorCode(new Numeral('1051')), new SqlState(new Text('42S02')), false];
    }
}
