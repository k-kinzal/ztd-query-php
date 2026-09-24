<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\SessionCommands;

#[CoversClass(SessionCommands::class)]
#[Medium]
final class SessionCommandsTest extends TestCase
{
    #[TestWith(['SHOW work_mem', 'SHOW "work_mem"'])]
    #[TestWith(['SHOW ALL', 'SHOW ALL'])]
    #[TestWith(['SET CONSTRAINTS a.b, c IMMEDIATE', 'SET CONSTRAINTS "a"."b", "c" IMMEDIATE'])]
    #[TestWith(["LOAD 'lib'", "LOAD 'lib'"])]
    #[TestWith(["DO LANGUAGE sql 'x'", 'DO \'x\' LANGUAGE "sql"'])]
    #[TestWith(['CALL p(1, VARIADIC a := ARRAY[1])', 'CALL "p"(1, VARIADIC "a" => ARRAY[1])'])]
    public function testWriteUsesTheCanonicalSpelling(string $sql, string $expected): void
    {
        self::assertSame($expected, SessionCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testArgumentMarksNamedAndVariadicNotation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, VARIADIC a => ARRAY[2])');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Procedural\PostgreSql\CallProcedureStatement::class, $statement);
        self::assertSame('1', SessionCommands::argument($statement->arguments[0])->toString());
        self::assertSame('VARIADIC "a" => ARRAY[2]', SessionCommands::argument($statement->arguments[1])->toString());
    }

    public function testWriteReturnsNullForOtherOperations(): void
    {
        self::assertNull(SessionCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('RESET ALL')));
    }
}
