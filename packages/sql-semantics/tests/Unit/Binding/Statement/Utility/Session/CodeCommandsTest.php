<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\Session\CodeCommands;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Execution\DoBlockStatement;
use SqlSemantics\Model\Statement\Loading\LoadLibraryStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CodeCommands::class)]
#[Medium]
final class CodeCommandsTest extends TestCase
{
    #[TestWith(["LOAD 'auto_explain'", "LOAD 'auto_explain'"])]
    #[TestWith(["LOAD E'lib\\\\x'", "LOAD E'lib\\\\x'"])]
    #[TestWith(['LOAD $$lib$$', 'LOAD $$lib$$'])]
    public function testLoadKeepsTheFileSpelling(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(LoadLibraryStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(["DO 'a''b'", null, "DO 'a''b'"])]
    #[TestWith(['DO LANGUAGE plpgsql $x$BEGIN END$x$', 'plpgsql', 'DO $x$BEGIN END$x$ LANGUAGE "plpgsql"'])]
    #[TestWith(["DO 'x' LANGUAGE 'Plx'", 'Plx', 'DO \'x\' LANGUAGE "Plx"'])]
    public function testBlockReadsTheCodeAndLanguage(string $sql, ?string $language, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(DoBlockStatement::class, $statement);
        self::assertSame($language, $statement->language);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['DO LANGUAGE plpgsql'])]
    #[TestWith(["DO 'a' 'b'"])]
    #[TestWith(["DO 'a' LANGUAGE x LANGUAGE y"])]
    public function testBlockRejectsMissingOrRepeatedItems(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AnonymousBlock->message());
        $binder->bind($sql);
    }
}
