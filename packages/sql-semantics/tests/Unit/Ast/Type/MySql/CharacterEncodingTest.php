<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\StringStorage;

#[CoversClass(\SqlSemantics\Ast\Type\MySql\CharacterEncoding::class)]
#[Medium]
final class CharacterEncodingTest extends TestCase
{
    #[TestWith(['ASCII', 'latin1', false])]
    #[TestWith(['ASCII BINARY', 'latin1', true])]
    #[TestWith(['BINARY ASCII', 'latin1', true])]
    #[TestWith(['UNICODE', 'ucs2', false])]
    #[TestWith(['UNICODE BINARY', 'ucs2', true])]
    #[TestWith(['BYTE', 'binary', false])]
    #[TestWith(['CHARSET BINARY', 'BINARY', false])]
    #[TestWith(['CHAR SET utf8mb4 BINARY', 'utf8mb4', true])]
    #[TestWith(['BINARY CHARACTER SET utf8mb4', 'utf8mb4', true])]
    public function testReadSeparatesCharacterSetIdentityFromBinaryCollation(string $clause, string $name, bool $binary): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(x CHAR(12) ' . $clause . ')');
        $type = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(StringStorage::class, $type);
        self::assertSame($name, $type->characterSet);
        self::assertSame($binary, $type->binary);
    }

    public function testReadDecodesLiteralNamesBeforeQuotingThemAsIdentifiers(): void
    {
        $builder = new SchemaBuilder(Dialect::MySql);
        $type = $builder->build('CREATE TABLE t(x CHAR(12) CHARSET \'a\'\'b\\\\x\')')->tables[0]->columns[0]->type;
        self::assertInstanceOf(StringStorage::class, $type->identity);
        self::assertSame("a'b\\x", $type->identity->characterSet);
        $sql = \SqlSemantics\Serialization\TypeDeclaration::write($type)->toString();
        self::assertStringContainsString("`a'b\\x`", $sql);
        $copy = $builder->build('CREATE TABLE t(x ' . $sql . ')')->tables[0]->columns[0]->type;
        self::assertEquals($type->identity, $copy->identity);
    }

}
