<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(GeneralCondition::class)]
#[Medium]
final class GeneralConditionTest extends TestCase
{
    /**
     * @return iterable<string, array{ConditionClass, string}>
     */
    public static function providerRenderWritesTheKeywordsOfTheClass(): iterable
    {
        yield 'warning' => [ConditionClass::SqlWarning, 'SQLWARNING'];
        yield 'not found' => [ConditionClass::NotFound, 'NOT FOUND'];
        yield 'exception' => [ConditionClass::SqlException, 'SQLEXCEPTION'];
    }

    #[DataProvider('providerRenderWritesTheKeywordsOfTheClass')]
    public function testRenderWritesTheKeywordsOfTheClass(ConditionClass $class, string $expected): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new GeneralCondition($class))->render($out);

        self::assertSame($expected, (new Lexical())->join($out->pieces()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRenderWritesTheClassesAHandlerNames(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerRenderWritesTheClassesAHandlerNames')]
    public function testRenderWritesTheClassesAHandlerNames(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() begin declare continue handler for sqlwarning, not   found, sqlexception begin end; end');
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $handler = $body->declarations[0];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);

        self::assertEquals(
            [new GeneralCondition(ConditionClass::SqlWarning), new GeneralCondition(ConditionClass::NotFound), new GeneralCondition(ConditionClass::SqlException)],
            $handler->conditions,
        );
        self::assertSame([], $create->facts->diagnostics);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLWARNING, NOT FOUND, SQLEXCEPTION BEGIN END; END', $create->toString());
    }
}
