<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(VariableDeclaration::class)]
#[Medium]
final class VariableDeclarationTest extends TestCase
{
    public function testDeriveRelationAnswersANullablePositionPerName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE a, b VARCHAR(5) DEFAULT \'x\'; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $declaration = $block->declarations[0];
        self::assertInstanceOf(VariableDeclaration::class, $declaration);
        $slots = $operation->facts->relation($declaration)->shape->slots;

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame(['a', 'b'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $slots));
        self::assertSame([Nullability::Nullable, Nullability::Nullable], array_map(static fn (OutputSlot $slot): Nullability => $slot->nullability, $slots));
        self::assertEquals(new Known($declaration->type), $slots[0]->type);
    }

    public function testDeriveRelationDerivesTheDefaultBeforeTheDeclaration(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) BEGIN DECLARE b INT DEFAULT a + c; END');

        self::assertSame(['Column c does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
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
        yield 'several names with a default' => ['mysql-9.1.0', 'create procedure p() begin declare a, b int default 0; end', 'CREATE PROCEDURE p() BEGIN DECLARE a, b INT DEFAULT 0; END'];
        yield 'a character set and a collation' => ['mysql-8.4.7', 'create procedure p() begin declare x varchar(10) character set utf8mb4 collate utf8mb4_bin default "a"; end', 'CREATE PROCEDURE p() BEGIN DECLARE x VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT \'a\'; END'];
        yield 'a collation in 5.6' => ['mysql-5.6.51', 'create procedure p() begin declare x text collate utf8_bin; end', 'CREATE PROCEDURE p() BEGIN DECLARE x TEXT COLLATE utf8_bin; END'];
        yield 'type synonyms in 8.0' => ['mysql-8.0.44', 'create procedure p() begin declare y double precision; declare z national char(2); end', 'CREATE PROCEDURE p() BEGIN DECLARE y DOUBLE; DECLARE z NCHAR(2); END'];
        yield 'JSON in 5.7' => ['mysql-5.7.44', 'create procedure p() begin declare x json default null; end', 'CREATE PROCEDURE p() BEGIN DECLARE x JSON DEFAULT NULL; END'];
        yield 'a display width in 5.6' => ['mysql-5.6.51', 'create procedure p() begin declare x int(11) unsigned zerofill default 1; end', 'CREATE PROCEDURE p() BEGIN DECLARE x INT(11) UNSIGNED ZEROFILL DEFAULT 1; END'];
    }

    public function testADeclarationWithoutNamesIsRejected(): void
    {
        $this->expectExceptionMessage('DECLARE names at least one variable.');

        new VariableDeclaration([], new Integral(IntegralKind::Int));
    }
}
