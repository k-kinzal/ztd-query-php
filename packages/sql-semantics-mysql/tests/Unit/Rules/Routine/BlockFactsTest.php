<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\BlockFacts;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(BlockFacts::class)]
#[Medium]
final class BlockFactsTest extends TestCase
{
    public function testDerivePreservesTheProgramsDatabaseThroughNestedVariableScopes(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE d.p() BEGIN DECLARE x INT; BEGIN DECLARE y INT; DROP TABLE t, d.t; END; END');
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $conditions = array_values(array_filter($statement->diagnostics(), static fn (Diagnostic $condition): bool => $condition instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable));
        self::assertCount(1, $conditions);
        self::assertSame('Table d.t is named more than once.', $conditions[0]->message());
    }

    public function testDeriveResolvesTheVariableOfTheInnermostBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; BEGIN DECLARE x CHAR(1); SELECT x; END; END');
        $create = $operation;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $outer = $create->body;
        self::assertInstanceOf(Block::class, $outer);
        $inner = $outer->statements[0];
        self::assertInstanceOf(Block::class, $inner);
        $select = $inner->statements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $resolution = $item->expression->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->diagnostics());
        self::assertSame($inner->declarations[0], $resolution->relation);
    }

    public function testDeriveEndsTheScopeOfTheDeclarationsWithTheBlock(): void
    {

        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; END; SELECT x; OPEN c; END');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('Undefined CURSOR: c', $notices[0]->message());

    }

    public function testDeriveDerivesTheCursorQueryInTheScopeOfTheBlock(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT a FROM t WHERE a = x; OPEN c; END', [$table]);
        $create = $operation;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $cursor = $block->declarations[1];
        self::assertInstanceOf(CursorDeclaration::class, $cursor);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $cursor->query);
        $query = $cursor->query->query;
        self::assertInstanceOf(Select::class, $query);
        $where = $query->where;
        self::assertInstanceOf(Comparison::class, $where);
        $resolution = $where->right->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->diagnostics());
        self::assertSame($block->declarations[0], $resolution->relation);
        self::assertSame(['Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT zz FROM t; END', [$table])->diagnostics()));
    }

    public function testDeriveDerivesTheHandlerStatementWithTheDeclarationsBeforeIt(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertSame([], $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN SET x = 1; CLOSE c; END; END')->diagnostics());
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() b: BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION LEAVE b; END b');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('LEAVE with no matching label: b', $notices[0]->message());

    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveReportsTheBrokenRule')]
    public function testDeriveReportsTheBrokenRule(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerDeriveReportsTheBrokenRule(): iterable
    {
        yield 'an unknown name in a cursor query' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT z; END', ['Column z does not exist.']];
        yield 'an unknown name in the statements' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; SELECT x, z; END', ['Column z does not exist.']];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerOrderedReportsADeclarationAfterOneItMustPrecede')]
    public function testOrderedReportsADeclarationAfterOneItMustPrecede(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerOrderedReportsADeclarationAfterOneItMustPrecede(): iterable
    {
        yield 'every kind in order' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE e CONDITION FOR 1051; DECLARE c CURSOR FOR SELECT x; DECLARE d CURSOR FOR SELECT 1; DECLARE EXIT HANDLER FOR e CLOSE c; DECLARE CONTINUE HANDLER FOR SQLWARNING CLOSE d; END', []];
        yield 'a condition before a variable' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE x INT; END', []];
        yield 'each block on its own' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; BEGIN DECLARE x INT; END; END', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDistinctComparesNamesWithoutRegardToLetterCase')]
    public function testDistinctComparesNamesWithoutRegardToLetterCase(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDistinctComparesNamesWithoutRegardToLetterCase(): iterable
    {
        yield 'distinct conditions and cursors' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE f CONDITION FOR 1052; DECLARE c CURSOR FOR SELECT 1; DECLARE d CURSOR FOR SELECT 2; END', []];
        yield 'a condition and a cursor of one name' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE e CURSOR FOR SELECT 1; END', []];
        yield 'the same names in a nested block' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE c CURSOR FOR SELECT 1; BEGIN DECLARE e CONDITION FOR 1052; DECLARE c CURSOR FOR SELECT 2; END; END', []];
    }

    public function testVariablesRejectsADuplicateNameBeforePublication(): void
    {

        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; DECLARE y, X CHAR(1); SELECT x, y; END');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('Duplicate variable: X', $notices[0]->message());

    }

    public function testVariablesReportsANameRepeatedInOneDeclaration(): void
    {

        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x, y, x INT; END');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('Duplicate variable: x', $notices[0]->message());

    }

    public function testVariablesMakesTheNamesVisibleAfterTheDeclarationOnly(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; DECLARE y INT DEFAULT x; END')->diagnostics());
        self::assertSame(['Column y does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x, y INT DEFAULT y; END')->diagnostics()));
    }

    public function testVariablesHidesAParameterOfTheSameName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) BEGIN DECLARE x CHAR(1); SELECT x; END');
        $create = $operation;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $select = $block->statements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        self::assertInstanceOf(ColumnUse::class, $item->expression);
        $resolution = $item->expression->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->diagnostics());
        self::assertSame($block->declarations[0], $resolution->relation);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerHandledReportsAConditionValueHandledTwiceInTheBlock')]
    public function testHandledReportsAConditionValueHandledTwiceInTheBlock(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerHandledReportsAConditionValueHandledTwiceInTheBlock(): iterable
    {
        yield 'distinct values' => ['mysql-9.1.0', "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 1051, SQLSTATE '42S02', NOT FOUND BEGIN END; DECLARE EXIT HANDLER FOR SQLWARNING, SQLEXCEPTION BEGIN END; END", []];
        yield 'the same value in a nested block' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 1051 BEGIN END; BEGIN DECLARE CONTINUE HANDLER FOR 1051 BEGIN END; END; END', []];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeriveRejectsUnboundHandlerNamesBeforePublication(): iterable
    {
        yield 'one missing condition' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR c BEGIN END; END'];
        yield 'the same missing condition twice' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR c, c BEGIN END; END'];
    }

    #[DataProvider('providerDeriveRejectsUnboundHandlerNamesBeforePublication')]
    public function testDeriveRejectsUnboundHandlerNamesBeforePublication(string $sql): void
    {

        $statement = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('Undefined CONDITION: c', $notices[0]->message());

    }
    public function testDeriveRejectsAnUnboundHandlerFetchTarget(): void
    {

        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR NOT FOUND FETCH c INTO x, y; END');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame('Undeclared variable: y', $notices[0]->message());

    }


    #[DataProvider('providerDeriveReportsTheBrokenRuleRetainsDefinitionCondition')]
    public function testDeriveReportsTheBrokenRuleRetainsDefinitionCondition(string $release, string $sql, string $message): void
    {

        $statement = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame($message, $notices[0]->message());

    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerDeriveReportsTheBrokenRuleRetainsDefinitionCondition(): iterable
    {
        yield 'a duplicate variable in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; END', 'Duplicate variable: x'];
        yield 'a duplicate condition in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE C CONDITION FOR 1052; END', 'Duplicate condition: C'];
        yield 'a duplicate cursor in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE cur CURSOR FOR SELECT 1; DECLARE cur CURSOR FOR SELECT 2; END', 'Duplicate cursor: cur'];
    }


    #[DataProvider('providerOrderedReportsADeclarationAfterOneItMustPrecedeRetainsDefinitionCondition')]
    public function testOrderedReportsADeclarationAfterOneItMustPrecedeRetainsDefinitionCondition(string $sql, string $message): void
    {

        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame($message, $notices[0]->message());

    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerOrderedReportsADeclarationAfterOneItMustPrecedeRetainsDefinitionCondition(): iterable
    {
        yield 'a variable after a cursor' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE x INT; END', 'Variable or condition declaration after cursor or handler declaration'];
        yield 'a condition after a cursor' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE e CONDITION FOR 1051; END', 'Variable or condition declaration after cursor or handler declaration'];
        yield 'a variable after a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE x INT; END', 'Variable or condition declaration after cursor or handler declaration'];
        yield 'a cursor after a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE c CURSOR FOR SELECT 1; END', 'Cursor declaration after handler declaration'];
        yield 'a variable after a cursor and a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE x INT; END', 'Variable or condition declaration after cursor or handler declaration'];
    }


    #[DataProvider('providerDistinctComparesNamesWithoutRegardToLetterCaseRetainsDefinitionCondition')]
    public function testDistinctComparesNamesWithoutRegardToLetterCaseRetainsDefinitionCondition(string $sql, string $message): void
    {

        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame($message, $notices[0]->message());

    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDistinctComparesNamesWithoutRegardToLetterCaseRetainsDefinitionCondition(): iterable
    {
        yield 'conditions in another case' => ["CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE E CONDITION FOR SQLSTATE '42S02'; END", 'Duplicate condition: E'];
        yield 'cursors in another case' => ['CREATE PROCEDURE p() BEGIN DECLARE cur CURSOR FOR SELECT 1; DECLARE CUR CURSOR FOR SELECT 2; END', 'Duplicate cursor: CUR'];
    }


    #[DataProvider('providerHandledReportsAConditionValueHandledTwiceInTheBlockRetainsDefinitionCondition')]
    public function testHandledReportsAConditionValueHandledTwiceInTheBlockRetainsDefinitionCondition(string $release, string $sql, string $message): void
    {

        $statement = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->warnings, static fn ($notice): bool => $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure));
        self::assertNotEmpty($notices);
        self::assertSame($message, $notices[0]->message());

    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerHandledReportsAConditionValueHandledTwiceInTheBlockRetainsDefinitionCondition(): iterable
    {
        yield 'a general condition of two handlers in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN END; END', 'Duplicate handler declared in the same block'];
        yield 'one error number in two spellings in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 1051, 0x41b BEGIN END; END', 'Duplicate handler declared in the same block'];
        yield 'a condition name for a handled number' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR 1051 BEGIN END; DECLARE EXIT HANDLER FOR c BEGIN END; END', 'Duplicate handler declared in the same block'];
        yield 'one SQLSTATE value' => ['mysql-9.1.0', "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '42S02', SQLSTATE VALUE '42S02' BEGIN END; END", 'Duplicate handler declared in the same block'];
    }
}
