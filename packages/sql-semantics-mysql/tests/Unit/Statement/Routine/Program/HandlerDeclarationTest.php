<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(HandlerDeclaration::class)]
#[Medium]
final class HandlerDeclarationTest extends TestCase
{
    public function testRenderWritesTheActionTheConditionsAndTheStatement(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR e, NOT FOUND, 1146 BEGIN END; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $handler = $block->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);

        self::assertSame(HandlerAction::Continue, $handler->action);
        self::assertCount(3, $handler->conditions);
        self::assertInstanceOf(Block::class, $handler->statement);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR e, NOT FOUND, 1146 BEGIN END; END', $operation->toString());
    }

    #[DataProvider('providerRenderWritesTheHandler')]
    public function testRenderWritesTheHandler(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheHandler(): iterable
    {
        yield 'EXIT with an SQL statement in 5.6' => ['mysql-5.6.51', "create procedure p() begin declare exit handler for sqlstate value '42S02', sqlwarning select 1; end", "CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLSTATE '42S02', SQLWARNING SELECT 1; END"];
        yield 'CONTINUE with SET in 5.7' => ['mysql-5.7.44', 'create procedure p() begin declare x int; declare continue handler for sqlexception set x = 1; end', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET x = 1; END'];
        yield 'a labeled block in 9.1' => ['mysql-9.1.0', 'create procedure p() begin declare exit handler for not found h: begin leave h; end h; end', 'CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR NOT FOUND h: BEGIN LEAVE h; END h; END'];
    }

    public function testAHandlerWithoutConditionsIsRejected(): void
    {
        $this->expectExceptionMessage('A handler names at least one condition.');

        new HandlerDeclaration(HandlerAction::Exit, [], new Leave(new Name('a')));
    }

    public function testAStatementOfAnotherClassIsRejected(): void
    {
        $this->expectExceptionMessage('A member of a stored program is a program statement or an SQL statement.');

        new HandlerDeclaration(HandlerAction::Exit, [new GeneralCondition(ConditionClass::SqlWarning)], new NumberLiteral('1'));
    }
}
