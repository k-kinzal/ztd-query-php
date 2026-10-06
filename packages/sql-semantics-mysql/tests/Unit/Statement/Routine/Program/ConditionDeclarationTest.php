<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;

#[CoversClass(ConditionDeclaration::class)]
#[Medium]
final class ConditionDeclarationTest extends TestCase
{
    public function testRenderWritesTheNameAndTheErrorCode(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR 1051; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $declaration = $block->declarations[0];
        self::assertInstanceOf(ConditionDeclaration::class, $declaration);

        self::assertSame('gone', $declaration->name->value);
        self::assertInstanceOf(ErrorCode::class, $declaration->value);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR 1051; END', $operation->toString());
    }

    #[DataProvider('providerRenderWritesTheDeclaration')]
    public function testRenderWritesTheDeclaration(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDeclaration(): iterable
    {
        yield 'an SQLSTATE value in 5.6' => ['mysql-5.6.51', "create procedure p() begin declare e condition for sqlstate value '42S02'; end", "CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR SQLSTATE '42S02'; END"];
        yield 'an SQLSTATE value in 8.4' => ['mysql-8.4.7', "create procedure p() begin declare e condition for sqlstate '42S02'; end", "CREATE PROCEDURE p() BEGIN DECLARE e CONDITION FOR SQLSTATE '42S02'; END"];
        yield 'an error code in 9.1' => ['mysql-9.1.0', 'create procedure p() begin declare `e x` condition for 1146; end', 'CREATE PROCEDURE p() BEGIN DECLARE `e x` CONDITION FOR 1146; END'];
    }
}
