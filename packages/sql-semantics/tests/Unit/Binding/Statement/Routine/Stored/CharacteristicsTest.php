<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Stored\Characteristics;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Characteristics::class)]
#[Medium]
final class CharacteristicsTest extends TestCase
{
    public function testReadKeepsTheLastDeclarationOfEachProperty(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("CREATE PROCEDURE p() DETERMINISTIC NO SQL COMMENT 'a' LANGUAGE sql NOT DETERMINISTIC MODIFIES SQL DATA COMMENT 'b' BEGIN END");
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new Identifiers(Dialect::MySql), ''));
        [$characteristics, $language] = Characteristics::read(Tree::outer($tree, ['sp_c_chistics'])[0], $context);
        self::assertFalse($characteristics->deterministic);
        self::assertSame(SqlDataAccess::Modifies, $characteristics->dataAccess);
        self::assertSame("'b'", $characteristics->comment?->text);
        self::assertSame('SQL', $language);
    }

    public function testReadDefaultsWithoutCharacteristics(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new Identifiers(Dialect::MySql), ''));
        [$characteristics, $language] = Characteristics::read(null, $context);
        self::assertSame(SqlDataAccess::Contains, $characteristics->dataAccess);
        self::assertSame('SQL', $language);
    }

    public function testReadDiagnosesAnEmptyLanguage(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind("CREATE PROCEDURE p() LANGUAGE `` AS 'x'", strict: false);
    }

    #[TestWith(['create procedure p() comment \'c\' language sql not deterministic reads sql data sql security invoker begin end', \SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement::class, 'CREATE PROCEDURE `p`() COMMENT \'c\' READS SQL DATA SQL SECURITY INVOKER BEGIN END'])]
    #[TestWith(['create function f() returns int deterministic no sql return 1', \SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, 'CREATE FUNCTION `f`() RETURNS integer DETERMINISTIC NO SQL RETURN 1'])]
    #[TestWith(['CREATE FUNCTION f() RETURNS INT RETURN 1', \SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, 'CREATE FUNCTION `f`() RETURNS integer RETURN 1'])]
    #[TestWith(['alter procedure p sql security definer modifies sql data', \SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement::class, 'ALTER PROCEDURE `p` MODIFIES SQL DATA SQL SECURITY DEFINER'])]
    public function testReadSpellsLowercaseCharacteristics(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}
