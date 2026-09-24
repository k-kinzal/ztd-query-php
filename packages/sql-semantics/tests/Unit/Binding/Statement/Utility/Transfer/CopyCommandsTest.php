<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\Transfer\CopyCommands;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Loading\Copy;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyCommands::class)]
#[Medium]
final class CopyCommandsTest extends TestCase
{
    #[TestWith(['COPY t TO STDOUT', Copy\CopyToStatement::class, 'COPY "public"."t" TO STDOUT'])]
    #[TestWith(["COPY t (a, b) FROM STDIN WITH CSV HEADER FORCE NOT NULL a NULL AS 'n' WHERE a > 1", Copy\CopyFromStatement::class, 'COPY "public"."t"("a", "b") FROM STDIN WITH (FORMAT \'csv\', NULL \'n\', HEADER TRUE, FORCE_NOT_NULL("a")) WHERE ("a" > 1)'])]
    #[TestWith(["COPY t FROM PROGRAM 'cat' USING DELIMITERS '|'", Copy\CopyFromStatement::class, 'COPY "public"."t" FROM PROGRAM \'cat\' WITH (DELIMITER \'|\')'])]
    #[TestWith(['COPY (DELETE FROM t RETURNING a) TO STDIN', Copy\CopyQueryStatement::class, 'COPY(DELETE FROM "public"."t" RETURNING "a" AS "a") TO STDOUT'])]
    public function testBindSeparatesTheCopyForms(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testEndpointReadsFilesProgramsAndTheClient(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $file = $binder->bind("COPY t FROM '/tmp/t'");
        $program = $binder->bind("COPY t TO PROGRAM 'gzip'");
        self::assertInstanceOf(Copy\CopyFromStatement::class, $file);
        self::assertInstanceOf(Copy\CopyToStatement::class, $program);
        self::assertInstanceOf(Copy\Endpoint\CopyFile::class, $file->input);
        self::assertInstanceOf(Copy\Endpoint\CopyProgram::class, $program->destination);
    }

    public function testQueryBindsARowReturningStatement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY (UPDATE t SET a = 1 RETURNING a) TO STDOUT');
        self::assertInstanceOf(Copy\CopyQueryStatement::class, $statement);
        self::assertSame('a', $statement->query->resultColumns()[0]->name);
    }

    #[TestWith(['COPY t FROM PROGRAM STDIN'])]
    #[TestWith(['COPY t TO STDOUT WHERE a > 1'])]
    #[TestWith(['COPY (DELETE FROM t) TO STDOUT'])]
    #[TestWith(['COPY (SELECT 1 INTO x) TO STDOUT'])]
    #[TestWith(['COPY t FROM STDIN CSV CSV'])]
    #[TestWith(["COPY t TO STDOUT (FORMAT 'CSV')"])]
    #[TestWith(['COPY t TO STDOUT (HEADER match)'])]
    #[TestWith(['COPY t FROM STDIN (unknown_option)'])]
    #[TestWith(['COPY t (a, a) TO STDOUT'])]
    #[TestWith(["COPY BINARY t FROM STDIN DELIMITERS ','"])]
    public function testBindRejectsRequestsTheServerRejects(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CopyOption->message());
        $binder->bind($sql);
    }
}
