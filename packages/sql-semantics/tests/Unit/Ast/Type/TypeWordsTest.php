<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\TextParameter;

#[CoversClass(\SqlSemantics\Ast\Type\TypeWords::class)]
#[Medium]
final class TypeWordsTest extends TestCase
{
    public function testReadKeepsMySqlEncodingSeparateFromItsLength(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('CREATE TABLE t(x VARCHAR(12) CHARACTER SET utf8mb4 BINARY)');
        $type = Tree::outer($source, ['type'])[0];
        $words = \SqlSemantics\Ast\Type\TypeWords::read($type, Dialect::MySql);
        self::assertSame(['VARCHAR'], $words->words);
        self::assertInstanceOf(NumericParameter::class, $words->parameters[0]);
        self::assertSame('12', $words->parameters[0]->spelling);
        self::assertSame('utf8mb4', $words->characterSet);
        self::assertTrue($words->binary);
    }

    public function testReadRetainsTheOperandCategoriesOfParameterizedTypes(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t(x NUMERIC(\'12\', "2"))');
        $words = \SqlSemantics\Ast\Type\TypeWords::read(Tree::outer($source, ['Typename'])[0], Dialect::PostgreSql);
        self::assertSame(['NUMERIC'], $words->words);
        self::assertInstanceOf(TextParameter::class, $words->parameters[0]);
        self::assertInstanceOf(IdentifierParameter::class, $words->parameters[1]);
    }

    /**
     * @return iterable<string, array{Dialect, string, string, string}>
     */
    public static function providerReadSeparatesWordsFromAttributes(): iterable
    {
        return [
            'x VARCHAR UNSIGNED(10) (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x VARCHAR UNSIGNED(10))', 'typetoken', '[["VARCHAR"],1,true,null,false]'],
            'x INT SIGNED ZEROFILL (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x INT SIGNED ZEROFILL)', 'typetoken', '[["INT"],0,true,null,false]'],
            'x TEXT CHARSET "latin1" (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x TEXT CHARSET "latin1")', 'typetoken', '[["TEXT"],0,false,"latin1",false]'],
            'x NATIONAL CHARACTER VARYING(3) (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x NATIONAL CHARACTER VARYING(3))', 'typetoken', '[["NATIONAL","CHARACTER","VARYING"],1,false,null,false]'],
            'x CHAR BINARY (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x CHAR BINARY)', 'typetoken', '[["CHAR"],0,false,null,true]'],
            'x BINARY (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x BINARY)', 'typetoken', '[["BINARY"],0,false,null,false]'],
            'x decimal unsigned(10, 2) (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x decimal unsigned(10, 2))', 'typetoken', '[["DECIMAL"],2,true,null,false]'],
            'x text charset `a` binary (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x text charset `a` binary)', 'typetoken', '[["TEXT"],0,false,"a",true]'],
            'x character varying (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x character varying)', 'typetoken', '[["CHARACTER","VARYING"],0,false,null,false]'],
            'x text charset \'b\' signed (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x text charset \'b\' signed)', 'typetoken', '[["TEXT"],0,false,"b",false]'],
            'x Binary Text (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x Binary Text)', 'typetoken', '[["BINARY","TEXT"],0,false,null,false]'],
            'x unsigned (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x unsigned)', 'typetoken', '[[],0,true,null,false]'],
            'x text character utf8 big (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x text character utf8 big)', 'typetoken', '[["TEXT","BIG"],0,false,"utf8",false]'],
            'x national charset utf8 (Sqlite)' => [Dialect::Sqlite, 'CREATE TABLE t(x national charset utf8)', 'typetoken', '[["NATIONAL","CHARSET","UTF8"],0,false,null,false]'],
            'x numeric(10, 2) (PostgreSql)' => [Dialect::PostgreSql, 'CREATE TABLE t(x numeric(10, 2))', 'Typename', '[["NUMERIC"],2,false,null,false]'],
            'x character varying(3) (PostgreSql)' => [Dialect::PostgreSql, 'CREATE TABLE t(x character varying(3))', 'Typename', '[["CHARACTER","VARYING"],1,false,null,false]'],
            'x varchar (PostgreSql)' => [Dialect::PostgreSql, 'CREATE TABLE t(x varchar)', 'Typename', '[["VARCHAR"],0,false,null,false]'],
            'x timestamp(3) with time zone (PostgreSql)' => [Dialect::PostgreSql, 'CREATE TABLE t(x timestamp(3) with time zone)', 'Typename', '[["TIMESTAMP","WITH","TIME","ZONE"],1,false,null,false]'],
            'x bit varying(4) (PostgreSql)' => [Dialect::PostgreSql, 'CREATE TABLE t(x bit varying(4))', 'Typename', '[["BIT","VARYING"],1,false,null,false]'],
        ];
    }

    #[DataProvider('providerReadSeparatesWordsFromAttributes')]
    public function testReadSeparatesWordsFromAttributes(Dialect $dialect, string $sql, string $node, string $expected): void
    {
        $words = \SqlSemantics\Ast\Type\TypeWords::read(Tree::outer((new DialectParser($dialect))->parse($sql), [$node])[0], $dialect);
        self::assertSame($expected, json_encode([$words->words, count($words->parameters), $words->unsigned, $words->characterSet, $words->binary]));
    }
}
