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
    public function testDeriveResolvesTheVariableOfTheInnermostBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; BEGIN DECLARE x CHAR(1); SELECT x; END; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $outer = $create->body;
        self::assertInstanceOf(Block::class, $outer);
        $inner = $outer->statements[0];
        self::assertInstanceOf(Block::class, $inner);
        $select = $inner->statements[0];
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $resolution = $operation->facts->scalar($item->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame($inner->declarations[0], $resolution->relation);
    }

    public function testDeriveEndsTheScopeOfTheDeclarationsWithTheBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; END; SELECT x; OPEN c; END');

        self::assertSame(['Column x does not exist.', 'Undefined CURSOR: c'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testDeriveDerivesTheCursorQueryInTheScopeOfTheBlock(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT a FROM t WHERE a = x; OPEN c; END', [$table]);
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $cursor = $block->declarations[1];
        self::assertInstanceOf(CursorDeclaration::class, $cursor);
        $query = $cursor->query;
        self::assertInstanceOf(Select::class, $query);
        $where = $query->where;
        self::assertInstanceOf(Comparison::class, $where);
        $resolution = $operation->facts->scalar($where->right)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame($block->declarations[0], $resolution->relation);
        self::assertSame(['Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT zz FROM t; END', [$table])->facts->diagnostics));
    }

    public function testDeriveDerivesTheHandlerStatementWithTheDeclarationsBeforeIt(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR NOT FOUND BEGIN SET x = 1; CLOSE c; END; END')->facts->diagnostics);
        self::assertSame(['Undeclared variable: y'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR NOT FOUND FETCH c INTO x, y; END')->facts->diagnostics));
        self::assertSame(['LEAVE with no matching label: b'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE PROCEDURE p() b: BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION LEAVE b; END b')->facts->diagnostics));
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveReportsTheBrokenRule')]
    public function testDeriveReportsTheBrokenRule(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerDeriveReportsTheBrokenRule(): iterable
    {
        yield 'a duplicate variable in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; END', ['Duplicate variable: x']];
        yield 'a duplicate condition in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE C CONDITION FOR 1052; END', ['Duplicate condition: C']];
        yield 'a duplicate cursor in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE cur CURSOR FOR SELECT 1; DECLARE cur CURSOR FOR SELECT 2; END', ['Duplicate cursor: cur']];
        yield 'an undefined handler condition' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR c BEGIN END; END', ['Undefined CONDITION: c']];
        yield 'a bad SQLSTATE of a handler' => ['mysql-9.1.0', "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '00000' BEGIN END; END", ["Bad SQLSTATE: '00000'"]];
        yield 'a bad SQLSTATE of a condition' => ['mysql-5.7.44', "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '00123'; END", ["Bad SQLSTATE: '00123'"]];
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

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerOrderedReportsADeclarationAfterOneItMustPrecede(): iterable
    {
        yield 'every kind in order' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE e CONDITION FOR 1051; DECLARE c CURSOR FOR SELECT x; DECLARE d CURSOR FOR SELECT 1; DECLARE EXIT HANDLER FOR e CLOSE c; DECLARE CONTINUE HANDLER FOR SQLWARNING CLOSE d; END', []];
        yield 'a condition before a variable' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE x INT; END', []];
        yield 'a variable after a cursor' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE x INT; END', ['Variable or condition declaration after cursor or handler declaration']];
        yield 'a condition after a cursor' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE e CONDITION FOR 1051; END', ['Variable or condition declaration after cursor or handler declaration']];
        yield 'a variable after a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE x INT; END', ['Variable or condition declaration after cursor or handler declaration']];
        yield 'a cursor after a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE c CURSOR FOR SELECT 1; END', ['Cursor declaration after handler declaration']];
        yield 'a variable after a cursor and a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE x INT; END', ['Variable or condition declaration after cursor or handler declaration']];
        yield 'each block on its own' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; BEGIN DECLARE x INT; END; END', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDistinctComparesNamesWithoutRegardToLetterCase')]
    public function testDistinctComparesNamesWithoutRegardToLetterCase(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDistinctComparesNamesWithoutRegardToLetterCase(): iterable
    {
        yield 'conditions in another case' => ["CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE E CONDITION FOR SQLSTATE '42S02'; END", ['Duplicate condition: E']];
        yield 'cursors in another case' => ['CREATE PROCEDURE p() BEGIN DECLARE cur CURSOR FOR SELECT 1; DECLARE CUR CURSOR FOR SELECT 2; END', ['Duplicate cursor: CUR']];
        yield 'distinct conditions and cursors' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE f CONDITION FOR 1052; DECLARE c CURSOR FOR SELECT 1; DECLARE d CURSOR FOR SELECT 2; END', []];
        yield 'a condition and a cursor of one name' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE e CURSOR FOR SELECT 1; END', []];
        yield 'the same names in a nested block' => ['CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE c CURSOR FOR SELECT 1; BEGIN DECLARE e CONDITION FOR 1052; DECLARE c CURSOR FOR SELECT 2; END; END', []];
    }

    public function testVariablesReportsADuplicateNameAndKeepsTheFirstMeaning(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; DECLARE y, X CHAR(1); SELECT x, y; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $select = $block->statements[0];
        self::assertInstanceOf(Select::class, $select);
        $first = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $first);
        $second = $select->items[1];
        self::assertInstanceOf(SelectExpression::class, $second);
        $x = $operation->facts->scalar($first->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $x);
        $y = $operation->facts->scalar($second->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $y);

        self::assertSame(['Duplicate variable: X'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertSame($block->declarations[0], $x->relation);
        self::assertSame($block->declarations[1], $y->relation);
    }

    public function testVariablesReportsANameRepeatedInOneDeclaration(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x, y, x INT; END');

        self::assertSame(['Duplicate variable: x'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testVariablesMakesTheNamesVisibleAfterTheDeclarationOnly(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; DECLARE y INT DEFAULT x; END')->facts->diagnostics);
        self::assertSame(['Column y does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE PROCEDURE p() BEGIN DECLARE x, y INT DEFAULT y; END')->facts->diagnostics));
    }

    public function testVariablesHidesAParameterOfTheSameName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) BEGIN DECLARE x CHAR(1); SELECT x; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $select = $block->statements[0];
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        self::assertInstanceOf(ColumnUse::class, $item->expression);
        $resolution = $operation->facts->scalar($item->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame($block->declarations[0], $resolution->relation);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerHandledReportsAConditionValueHandledTwiceInTheBlock')]
    public function testHandledReportsAConditionValueHandledTwiceInTheBlock(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerHandledReportsAConditionValueHandledTwiceInTheBlock(): iterable
    {
        yield 'a general condition of two handlers in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN END; END', ['Duplicate handler declared in the same block']];
        yield 'one error number in two spellings in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 1051, 0x41b BEGIN END; END', ['Duplicate handler declared in the same block']];
        yield 'a condition name for a handled number' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR 1051 BEGIN END; DECLARE EXIT HANDLER FOR c BEGIN END; END', ['Duplicate handler declared in the same block']];
        yield 'one SQLSTATE value' => ['mysql-9.1.0', "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '42S02', SQLSTATE VALUE '42S02' BEGIN END; END", ['Duplicate handler declared in the same block']];
        yield 'distinct values' => ['mysql-9.1.0', "CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 1051, SQLSTATE '42S02', NOT FOUND BEGIN END; DECLARE EXIT HANDLER FOR SQLWARNING, SQLEXCEPTION BEGIN END; END", []];
        yield 'the same value in a nested block' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 1051 BEGIN END; BEGIN DECLARE CONTINUE HANDLER FOR 1051 BEGIN END; END; END', []];
        yield 'an undefined condition name twice' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR c, c BEGIN END; END', ['Undefined CONDITION: c', 'Undefined CONDITION: c']];
    }
}
