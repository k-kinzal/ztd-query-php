<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Statement\Definition\MySql\AlterDatabaseStatement;
use SqlSemantics\Model\Statement\Definition\MySql\CreateDatabaseStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Database\DatabaseOperands::class)]
#[Medium]
final class DatabaseOperandsTest extends TestCase
{
    public function testNameDecodesTextRatherThanKeepingItsEscapes(): void
    {
        $token = new \SqlParser\Lexer\Token(0, 'TEXT_STRING', "'ut\\f8mb4'", 0);
        self::assertSame('utf8mb4', \SqlSemantics\Binding\Statement\Definition\Database\DatabaseOperands::name($token, new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }

    #[TestWith(['0', DatabaseReadOnly::Disabled])]
    #[TestWith(['DEFAULT', DatabaseReadOnly::Disabled])]
    #[TestWith(['0001', DatabaseReadOnly::Enabled])]
    #[TestWith(['1.0', DatabaseReadOnly::Enabled])]
    #[TestWith(['1e9', DatabaseReadOnly::Enabled])]
    #[TestWith(['.5', DatabaseReadOnly::Disabled])]
    #[TestWith(['0x01', DatabaseReadOnly::Enabled])]
    #[TestWith(["X'00'", DatabaseReadOnly::Disabled])]
    public function testReadOnlyNormalizesEquivalentLexicalRequests(string $value, DatabaseReadOnly $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DATABASE app READ ONLY ' . $value);
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        self::assertSame([$expected], $statement->options);
    }

    #[TestWith(['2'])]
    #[TestWith(['2.0'])]
    #[TestWith(['0x2'])]
    public function testReadOnlyRejectsValuesOutsideItsDomain(string $value): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DATABASE app READ ONLY ' . $value);
    }

    #[TestWith(['y', DatabaseEncryption::Enabled])]
    #[TestWith(['N', DatabaseEncryption::Disabled])]
    #[TestWith(['\\Y', DatabaseEncryption::Enabled])]
    public function testEncryptionNormalizesItsCaseInsensitiveLiteralDomain(string $value, DatabaseEncryption $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE DATABASE app ENCRYPTION '" . $value . "'");
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        self::assertSame([$expected], $statement->options);
    }

    public function testEncryptionRejectsArbitraryText(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE DATABASE app ENCRYPTION 'yes'");
    }
}
