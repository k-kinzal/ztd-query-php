<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Mutation;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\GeneratedWrites;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\MutationScope;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\WriteKind;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedWrites::class)]
#[Medium]
final class GeneratedWritesTest extends TestCase
{
    public function testWritableCountsTheColumnsThatAreNotGenerated(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $shapes = new TableShapes();
        $rules = new GeneratedWrites();

        self::assertSame(1, $rules->writable($shapes->shape($semantics->analyze('CREATE TABLE t (a, b AS (a + 1), c GENERATED ALWAYS AS (a * 2) STORED)')->declarations()[0])));
        self::assertSame(2, $rules->writable($shapes->shape($semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a)')->declarations()[0])));
        self::assertSame(1, $rules->writable($shapes->shape($semantics->analyze('CREATE TABLE c AS SELECT 1 AS b')->declarations()[0])));
    }

    #[TestWith(['INSERT INTO t (b) VALUES (1)', 'cannot INSERT into generated column "b"'])]
    #[TestWith(['INSERT INTO t (a, C) VALUES (1, 2)', 'cannot INSERT into generated column "c"'])]
    #[TestWith(['INSERT INTO t (a, b) VALUES (1, 2)', 'cannot INSERT into generated column "b"'])]
    #[TestWith(['INSERT INTO t (b) SELECT 1 WHERE 0', 'cannot INSERT into generated column "b"'])]
    public function testInsertedReportsTheGeneratedColumnsOfTheColumnList(string $sql, string $message): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a, b AS (a + 1), c GENERATED ALWAYS AS (a * 2) STORED)')]);
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE t (a, b AS (a + 1), c GENERATED ALWAYS AS (a * 2) STORED)');

        self::assertInstanceOf(GeneratedColumnWrite::class, $operation->facts->diagnostics[0] ?? null);
        self::assertSame(WriteKind::Insert, $operation->facts->diagnostics[0]->write);
        self::assertSame([$message], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage($message);
        $database->exec($sql);
    }

    #[TestWith(['INSERT INTO t VALUES (1)'])]
    #[TestWith(['INSERT INTO t SELECT a FROM t'])]
    #[TestWith(['INSERT INTO t (rowid, a) VALUES (1, 2)'])]
    #[TestWith(['INSERT INTO t DEFAULT VALUES'])]
    #[TestWith(['INSERT INTO t (a) VALUES (1) RETURNING b, c'])]
    #[TestWith(['UPDATE t SET a = b + c, rowid = 2'])]
    public function testInsertedAndAssignedReportNothingForWritesOfOtherColumns(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a, b AS (a + 1), c GENERATED ALWAYS AS (a * 2) STORED)')]);
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE t (a, b AS (a + 1), c GENERATED ALWAYS AS (a * 2) STORED)');

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertNotFalse($database->query($sql));
    }

    #[TestWith(['UPDATE t SET b = 1', 'cannot UPDATE generated column "b"'])]
    #[TestWith(['UPDATE t AS x SET a = 1, (a, C) = (1, 2)', 'cannot UPDATE generated column "c"'])]
    #[TestWith(['UPDATE t SET b = 1 FROM t AS y', 'cannot UPDATE generated column "b"'])]
    #[TestWith(['INSERT INTO t (rowid, a) VALUES (1, 1) ON CONFLICT DO UPDATE SET b = 1', 'cannot UPDATE generated column "b"'])]
    public function testAssignedReportsWhatSqliteRejectsInAnAssignment(string $sql, string $message): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a, b AS (a + 1), c GENERATED ALWAYS AS (a * 2) STORED)')]);
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE t (a, b AS (a + 1), c GENERATED ALWAYS AS (a * 2) STORED)');

        self::assertSame([$message], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertInstanceOf(GeneratedColumnWrite::class, $operation->facts->diagnostics[0]);
        self::assertSame(WriteKind::Update, $operation->facts->diagnostics[0]->write);
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage($message);
        $database->exec($sql);
    }

    public function testWrittenAnswersTheGeneratedColumnANameDenotes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (a, b AS (a + 1), rowid AS (a))');
        $statement = $semantics->analyze('UPDATE t SET a = 1', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));
        $rules = new GeneratedWrites();

        self::assertInstanceOf(Update::class, $statement);
        $target = (new MutationScope())->open($statement->target, null, $derivation, $derivation->environment())[1];
        self::assertSame($t->declarations()[0]->columns[1], $rules->written(new Name('B'), $target, $derivation));
        self::assertSame($t->declarations()[0]->columns[2], $rules->written(new Name('rowid'), $target, $derivation));
        self::assertNull($rules->written(new Name('a'), $target, $derivation));
        self::assertNull($rules->written(new Name('oid'), $target, $derivation));
        self::assertNull($rules->written(new Name('zz'), $target, $derivation));
    }

    #[TestWith(['INSERT INTO u VALUES (1, 2) ON CONFLICT DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO u (id, a) VALUES (1, 2) ON CONFLICT ((id)) DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO u (_rowid_, a) VALUES (1, 2) ON CONFLICT (u.oid DESC) DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO u (id, a) VALUES (1, 2) ON CONFLICT ("id") DO UPDATE SET b = 1 ON CONFLICT DO NOTHING'])]
    #[TestWith(['INSERT INTO k (a) VALUES (1) ON CONFLICT (a) DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO k (a) VALUES (1) ON CONFLICT (rowid) DO NOTHING ON CONFLICT (a) DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO w (a) VALUES (1) ON CONFLICT DO UPDATE SET b = 1'])]
    public function testReachedReportsTheDoUpdateSqliteCompiles(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $schema = 'CREATE TABLE u (id INTEGER PRIMARY KEY, a, b AS (a + 1)); CREATE TABLE k (a UNIQUE, b AS (a)); CREATE TABLE w (a PRIMARY KEY, b AS (a)) WITHOUT ROWID';
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE u (id INTEGER PRIMARY KEY, a, b AS (a + 1))'), $semantics->analyze('CREATE TABLE k (a UNIQUE, b AS (a))'), $semantics->analyze('CREATE TABLE w (a PRIMARY KEY, b AS (a)) WITHOUT ROWID')]);
        $database = new PDO('sqlite::memory:');
        $database->exec($schema);

        self::assertSame(['cannot UPDATE generated column "b"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('cannot UPDATE generated column "b"');
        $database->exec($sql);
    }

    #[TestWith(['INSERT INTO u (a) VALUES (1) ON CONFLICT (id) DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO u (a) VALUES (1) ON CONFLICT DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO u (id, a) VALUES (1, 1) ON CONFLICT (id) DO NOTHING ON CONFLICT DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO k (a) VALUES (1) ON CONFLICT (a) DO NOTHING ON CONFLICT (a) DO UPDATE SET b = 1'])]
    #[TestWith(['INSERT INTO u (a) VALUES (1) ON CONFLICT (rowid) DO UPDATE SET b = 1'])]
    public function testReachedLeavesTheDoUpdateSqliteNeverCompilesUnreported(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE u (id INTEGER PRIMARY KEY, a, b AS (a + 1))'), $semantics->analyze('CREATE TABLE k (a UNIQUE, b AS (a))')]);
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE u (id INTEGER PRIMARY KEY, a, b AS (a + 1)); CREATE TABLE k (a UNIQUE, b AS (a))');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNotFalse($database->exec($sql));
    }

    public function testReachedLeavesADoUpdateThatDependsOnUniqueIndexesUnreported(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $sql = 'INSERT INTO t (a) VALUES (1) ON CONFLICT DO UPDATE SET b = 1';
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a, b AS (a))')]);
        $plain = new PDO('sqlite::memory:');
        $plain->exec('CREATE TABLE t (a, b AS (a))');
        $indexed = new PDO('sqlite::memory:');
        $indexed->exec('CREATE TABLE t (a, b AS (a)); CREATE UNIQUE INDEX i ON t (a)');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNotFalse($plain->exec($sql));
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('cannot UPDATE generated column "b"');
        $indexed->exec($sql);
    }

    public function testRowidTargetRecognisesASingleNameOfTheRowid(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $u = $semantics->analyze('CREATE TABLE u (id INTEGER PRIMARY KEY, a, b AS (a + 1))');
        $statement = $semantics->analyze('INSERT INTO u VALUES (1, 2) ON CONFLICT ((id)) DO NOTHING ON CONFLICT (oid) DO NOTHING ON CONFLICT (id COLLATE nocase) DO NOTHING ON CONFLICT (id, a) DO NOTHING ON CONFLICT (a) DO NOTHING', [$u])->statement;
        $derivation = new Derivation($semantics->context([$u]));
        $rowid = $u->declarations()[0]->implicit[0]->column;
        $rules = new GeneratedWrites();

        self::assertInstanceOf(InsertRows::class, $statement);
        $environment = new Environment($derivation->context, null, [(new MutationScope())->open($statement->into->target, null, $derivation, $derivation->environment())[1]]);
        self::assertSame([true, true, false, false, false], array_map(static fn (Upsert $upsert): bool => $upsert->target !== null && $rules->rowidTarget($upsert->target, $rowid, $environment), $statement->upserts));
    }

    public function testSuppliedTellsWhetherTheInsertedRowSuppliesTheRowid(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $u = $semantics->analyze('CREATE TABLE u (id INTEGER PRIMARY KEY, a)');
        $t = $semantics->analyze('CREATE TABLE t (a, rowid)');
        $w = $semantics->analyze('CREATE TABLE w (a PRIMARY KEY) WITHOUT ROWID');
        $derivation = new Derivation($semantics->context([$u, $t, $w]));
        $scope = new MutationScope();
        $statement = $semantics->analyze('INSERT INTO u VALUES (1, 2)', [$u])->statement;
        $other = $semantics->analyze('INSERT INTO t VALUES (1, 2)', [$t])->statement;
        $keyed = $semantics->analyze('INSERT INTO w VALUES (1)', [$w])->statement;
        $rules = new GeneratedWrites();

        self::assertInstanceOf(InsertRows::class, $statement);
        self::assertInstanceOf(InsertRows::class, $other);
        self::assertInstanceOf(InsertRows::class, $keyed);
        $target = $scope->open($statement->into->target, null, $derivation, $derivation->environment())[1];
        $plain = $scope->open($other->into->target, null, $derivation, $derivation->environment())[1];
        $without = $scope->open($keyed->into->target, null, $derivation, $derivation->environment())[1];
        self::assertTrue($rules->supplied([], $u->declarations()[0], $target, $derivation));
        self::assertTrue($rules->supplied([new Name('a'), new Name('ID')], $u->declarations()[0], $target, $derivation));
        self::assertTrue($rules->supplied([new Name('_rowid_')], $u->declarations()[0], $target, $derivation));
        self::assertFalse($rules->supplied([new Name('a')], $u->declarations()[0], $target, $derivation));
        self::assertFalse($rules->supplied([], $t->declarations()[0], $plain, $derivation));
        self::assertFalse($rules->supplied([new Name('rowid')], $t->declarations()[0], $plain, $derivation));
        self::assertTrue($rules->supplied([new Name('oid')], $t->declarations()[0], $plain, $derivation));
        self::assertFalse($rules->supplied([new Name('a')], $w->declarations()[0], $without, $derivation));
    }
}
