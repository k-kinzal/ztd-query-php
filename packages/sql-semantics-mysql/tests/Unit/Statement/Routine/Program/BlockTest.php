<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(Block::class)]
#[Medium]
final class BlockTest extends TestCase
{
    public function testDeriveProgramResolvesAVariableDeclaredBefore(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; DECLARE y INT DEFAULT x + 1; SELECT y; END');

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveProgramKeepsTheFirstMeaningOfADuplicateVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 1; DECLARE X CHAR(1); SELECT x; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $select = $block->statements[0];
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $use = $item->expression;
        self::assertInstanceOf(ColumnUse::class, $use);
        $resolution = $operation->facts->scalar($use)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame(['Duplicate variable: X'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertSame($block->declarations[0], $resolution->relation);
    }

    public function testDeriveProgramHidesAParameterWithAVariableOfTheSameName(): void
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
        $use = $item->expression;
        self::assertInstanceOf(ColumnUse::class, $use);
        $resolution = $operation->facts->scalar($use)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame($block->declarations[0], $resolution->relation);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerDeriveProgramReportsTheBrokenRule')]
    public function testDeriveProgramReportsTheBrokenRule(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerDeriveProgramReportsTheBrokenRule(): iterable
    {
        yield 'a variable used in its own default' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT x; END', ['Column x does not exist.']];
        yield 'a duplicate condition' => ["CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE E CONDITION FOR SQLSTATE '42S02'; END", ['Duplicate condition: E']];
        yield 'a duplicate cursor' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE c CURSOR FOR SELECT 2; END', ['Duplicate cursor: c']];
        yield 'a variable after a cursor' => ['CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE x INT; END', ['Variable or condition declaration after cursor or handler declaration']];
        yield 'a condition after a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLWARNING BEGIN END; DECLARE e CONDITION FOR 1; END', ['Variable or condition declaration after cursor or handler declaration']];
        yield 'a cursor after a handler' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE c CURSOR FOR SELECT 1; END', ['Cursor declaration after handler declaration']];
        yield 'a handler before the cursor it uses' => ['CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLWARNING OPEN c; DECLARE c CURSOR FOR SELECT 1; END', ['Undefined CURSOR: c', 'Cursor declaration after handler declaration']];
        yield 'an undefined handler condition' => ['CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR nope BEGIN END; END', ['Undefined CONDITION: nope']];
        yield 'a redefined label' => ['CREATE PROCEDURE p() a: BEGIN a: BEGIN END; END', ['Redefining label a']];
        yield 'an end label without match' => ['CREATE PROCEDURE p() a: BEGIN END b', ['End-label b without match']];
        yield 'ITERATE of a block label' => ['CREATE PROCEDURE p() a: BEGIN ITERATE a; END', ['ITERATE with no matching label: a']];
        yield 'LEAVE of a block label from a handler' => ['CREATE PROCEDURE p() a: BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION LEAVE a; END a', ['LEAVE with no matching label: a']];
        yield 'a cursor of an inner block' => ['CREATE PROCEDURE p() BEGIN BEGIN DECLARE c CURSOR FOR SELECT 1; END; OPEN c; END', ['Undefined CURSOR: c']];
    }

    #[DataProvider('providerDeriveProgramAcceptsAWellFormedBlock')]
    public function testDeriveProgramAcceptsAWellFormedBlock(string $sql): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze($sql)->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeriveProgramAcceptsAWellFormedBlock(): iterable
    {
        yield 'every kind of declaration in order' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE e CONDITION FOR 1051; DECLARE c CURSOR FOR SELECT x; DECLARE EXIT HANDLER FOR e CLOSE c; DECLARE CONTINUE HANDLER FOR SQLWARNING SET x = 1; OPEN c; END'];
        yield 'the same names in a nested block' => ["CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE e CONDITION FOR 1051; DECLARE c CURSOR FOR SELECT 1; BEGIN DECLARE x CHAR(1); DECLARE e CONDITION FOR SQLSTATE '42S02'; DECLARE c CURSOR FOR SELECT 2; OPEN c; END; END"];
        yield 'LEAVE of an outer block' => ['CREATE PROCEDURE p() a: BEGIN b: BEGIN LEAVE a; END b; END a'];
        yield 'an end label in another case' => ['CREATE PROCEDURE p() a: BEGIN END A'];
        yield 'a condition name in another case' => ['CREATE PROCEDURE p() BEGIN DECLARE E CONDITION FOR 1051; DECLARE EXIT HANDLER FOR e BEGIN END; END'];
    }

    #[DataProvider('providerRenderWritesTheBlock')]
    public function testRenderWritesTheBlock(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheBlock(): iterable
    {
        yield 'an empty block' => ['mysql-9.1.0', 'create procedure p() begin end', 'CREATE PROCEDURE p() BEGIN END'];
        yield 'a labeled block in 5.6' => ['mysql-5.6.51', 'create procedure p() work: begin declare a int; select a; end work', 'CREATE PROCEDURE p() `work`: BEGIN DECLARE a INT; SELECT a; END `work`'];
        yield 'a label that is no keyword in 5.7' => ['mysql-5.7.44', 'create procedure p() w: begin end w', 'CREATE PROCEDURE p() w: BEGIN END w'];
        yield 'a label without end label in 5.7' => ['mysql-5.7.44', 'create procedure p() work: begin select 1; select 2; end', 'CREATE PROCEDURE p() `work`: BEGIN SELECT 1; SELECT 2; END'];
        yield 'a cursor over a WITH query in 8.0' => ['mysql-8.0.44', 'create procedure p() begin declare c cursor for with q as (select 1 as a) select a from q; end', 'CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR WITH q AS (SELECT 1 AS a) SELECT a FROM q; END'];
    }

    public function testAnEndLabelWithoutALabelIsRejected(): void
    {
        $this->expectExceptionMessage('Only a labeled block has an end label.');

        new Block([], [new Leave(new Name('a'))], null, new Name('a'));
    }

}
