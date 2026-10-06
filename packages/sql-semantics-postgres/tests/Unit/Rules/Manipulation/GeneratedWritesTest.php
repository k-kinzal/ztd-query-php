<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\GeneratedWrites;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(GeneratedWrites::class)]
#[Medium]
final class GeneratedWritesTest extends TestCase
{
    public function testInsertedReportsAValueForAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)');
        $insert = $semantics->analyze('INSERT INTO g VALUES (1, 2)', $table->declarations());
        self::assertEquals([new ManipulationMisuse(ManipulationMisuseRule::GeneratedInsert, 'b')], $insert->facts->diagnostics);
    }

    public function testInsertedAcceptsDefaultInEveryRow(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-16.6');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)');
        $insert = $semantics->analyze('INSERT INTO g (b, a) VALUES (DEFAULT, 1), ((DEFAULT), 2) RETURNING b', $table->declarations());
        self::assertSame([], $insert->facts->diagnostics);
    }

    public function testInsertedReportsAValueInOneOfTheRows(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)');
        $insert = $semantics->analyze('INSERT INTO g (b, a) VALUES (DEFAULT, 1), (2, 1)', $table->declarations());
        self::assertSame(['cannot insert a non-DEFAULT value into column "b"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $insert->facts->diagnostics));
    }

    public function testInsertedReportsTheRowsOfAQuery(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)');
        $insert = $semantics->analyze('INSERT INTO g (c, b) OVERRIDING SYSTEM VALUE SELECT 1, 2', $table->declarations());
        self::assertSame(['cannot insert a non-DEFAULT value into column "b"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $insert->facts->diagnostics));
    }

    public function testInsertedReportsTheInsertActionOfMerge(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $context = [...$semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)')->declarations(), ...$semantics->analyze('CREATE TABLE p (a int, c int)')->declarations()];
        $merge = $semantics->analyze('MERGE INTO g USING p ON g.a = p.a WHEN NOT MATCHED THEN INSERT VALUES (p.a, p.c)', $context);
        self::assertSame(['cannot insert a non-DEFAULT value into column "b"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $merge->facts->diagnostics));
    }

    public function testUpdatedReportsAValueForAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)');
        $update = $semantics->analyze('UPDATE g SET c = 1, (a, b) = (SELECT 1, 2)', $table->declarations());
        self::assertSame(['column "b" can only be updated to DEFAULT'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $update->facts->diagnostics));
    }

    public function testUpdatedAcceptsDefault(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED, c int)');
        $update = $semantics->analyze('UPDATE g SET b = (DEFAULT), (c, a) = ROW(1, DEFAULT)', $table->declarations());
        self::assertSame([], $update->facts->diagnostics);
    }

    public function testUpdatedReportsOnConflictDoUpdate(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int PRIMARY KEY, b int GENERATED ALWAYS AS (a * 2) STORED)');
        $insert = $semantics->analyze('INSERT INTO g (a) VALUES (1) ON CONFLICT (a) DO UPDATE SET b = excluded.b', $table->declarations());
        self::assertSame(['column "b" can only be updated to DEFAULT'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $insert->facts->diagnostics));
    }

    public function testCheckedIsFalseInTheActionOfARule(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $context = [...$semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED)')->declarations(), ...$semantics->analyze('CREATE TABLE p (a int)')->declarations()];
        $rule = $semantics->analyze('CREATE RULE r AS ON INSERT TO p DO ALSO INSERT INTO g VALUES (new.a, new.a)', $context);
        self::assertSame([], $rule->facts->diagnostics);
    }

    public function testTargetsFindsAColumnWrittenInPart(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int[], b int[] GENERATED ALWAYS AS (a) STORED)');
        $update = $semantics->analyze('UPDATE g SET b[1] = 1', $table->declarations());
        self::assertSame(['column "b" can only be updated to DEFAULT'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $update->facts->diagnostics));
    }

    public function testReportNamesTheFirstGeneratedColumnInTableOrder(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a) STORED, c int GENERATED ALWAYS AS (a) STORED)');
        $insert = $semantics->analyze('INSERT INTO g (c, b) VALUES (1, 2)', $table->declarations());
        self::assertSame(['cannot insert a non-DEFAULT value into column "b"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $insert->facts->diagnostics));
    }
}
