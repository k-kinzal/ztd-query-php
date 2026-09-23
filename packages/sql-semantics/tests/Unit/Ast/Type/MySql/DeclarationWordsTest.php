<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\StringStorage;

#[CoversClass(\SqlSemantics\Ast\Type\MySql\DeclarationWords::class)]
#[Medium]
final class DeclarationWordsTest extends TestCase
{
    #[TestWith(['MIDDLEINT ZEROFILL', 'mediumint unsigned', 'mysql-5.6.51'])]
    #[TestWith(['INT1', 'tinyint', 'mysql-5.7.44'])]
    #[TestWith(['INT2', 'smallint', 'mysql-5.6.51'])]
    #[TestWith(['INT8', 'bigint', 'mysql-5.7.44'])]
    #[TestWith(['TINYINT(3) UNSIGNED', 'tinyint unsigned'])]
    #[TestWith(['NUMERIC(12,2) ZEROFILL', 'numeric unsigned'])]
    #[TestWith(['FLOAT4(12,2)', 'float'])]
    #[TestWith(['FLOAT8(12,2)', 'double precision'])]
    #[TestWith(['FIXED(12,2)', 'numeric'])]
    #[TestWith(['INT1', 'tinyint'])]
    #[TestWith(['INTEGER', 'integer'])]
    #[TestWith(['SQL_TSI_YEAR', 'year'])]
    #[TestWith(['LONG VARBINARY', 'mediumblob'])]
    #[TestWith(['LONG CHARACTER VARYING', 'mediumtext'])]
    #[TestWith(['LONG', 'mediumtext'])]
    #[TestWith(['GEOMCOLLECTION', 'geometrycollection'])]
    public function testWordResolvesKeywordAliasesThroughTheirGrammarTerminal(string $declaration, string $expected, string $version = 'mysql-8.4.7'): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('CREATE TABLE t(x ' . $declaration . ')');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        self::assertSame($expected, $statement->definition->table->columns[0]->type->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testReadRetainsNationalEncodingAcrossGrammarReleases(string $version): void
    {
        $builder = new SchemaBuilder(Dialect::MySql, grammarVersion: $version);
        $type = $builder->build('CREATE TABLE t(x NATIONAL VARCHARACTER(12) BINARY)')->tables[0]->columns[0]->type;
        self::assertInstanceOf(StringStorage::class, $type->identity);
        self::assertSame(BuiltinIdentity::Varchar, $type->identity->base);
        self::assertTrue($type->identity->national);
        self::assertTrue($type->identity->binary);
        self::assertNull($type->identity->characterSet);
        $sql = \SqlSemantics\Serialization\TypeDeclaration::write($type)->toString();
        $copy = $builder->build('CREATE TABLE t(x ' . $sql . ')')->tables[0]->columns[0]->type;
        self::assertEquals($type->identity, $copy->identity);
    }

}
