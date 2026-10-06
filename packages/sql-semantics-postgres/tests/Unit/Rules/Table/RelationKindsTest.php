<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RelationKinds::class)]
#[Medium]
final class RelationKindsTest extends TestCase
{
    public function testOfAnswersTheKindOfTheResolvedDeclaration(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $statement = $semantics->analyze('REFRESH MATERIALIZED VIEW m', [$semantics->analyze('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a')]);
        self::assertSame(RelationKind::MaterializedView, (new RelationKinds())->of($statement->facts->relation($statement->statement)));
    }

    public function testDeclaredReportsNothingForAnUndeclaredRelation(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame([], $semantics->analyze('DROP VIEW t')->facts->diagnostics);
    }

    public function testRequireReportsAKindTheCommandDoesNotAccept(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a'), $semantics->analyze('CREATE SEQUENCE s'), $semantics->analyze('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a')];
        self::assertSame(['"v" is not a table', '"s" is not a table', '"m" is not a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('TRUNCATE v, s, m', $context)->facts->diagnostics));
    }

    public function testNamedReportsADropOfAnotherKind(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE t (a int)')];
        self::assertSame(['"t" is not a view'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('DROP VIEW IF EXISTS t', $context)->facts->diagnostics));
        self::assertSame(['"t" is not an index'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze("COMMENT ON INDEX t IS 'x'", $context)->facts->diagnostics));
    }

    public function testAlteredAcceptsEveryKindForAlterTable(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame([], $semantics->analyze('ALTER TABLE v RENAME TO w', $context)->facts->diagnostics);
        self::assertSame(['"v" is not a sequence'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('ALTER SEQUENCE v SET SCHEMA x', $context)->facts->diagnostics));
    }

    public function testRenamedRefusesRenamingAColumnOfASequence(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE SEQUENCE s')];
        self::assertSame(['cannot rename columns of relation "s"'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('ALTER TABLE s RENAME COLUMN log_cnt TO c', $context)->facts->diagnostics));
        self::assertSame([], $semantics->analyze('ALTER INDEX s RENAME TO i', $context)->facts->diagnostics);
    }

    public function testExpectedAnswersTheKindAndTheRule(): void
    {
        self::assertSame([[RelationKind::View, KindRule::NotView], [null, KindRule::NotIndex], null], [(new RelationKinds())->expected(ObjectKind::View), (new RelationKinds())->expected(ObjectKind::Index), (new RelationKinds())->expected(ObjectKind::Function)]);
    }

    public function testNameAnswersTheRelationADottedNameWrites(): void
    {
        $name = (new RelationKinds())->name(new DottedName([new Name('a'), new Name('t')]));
        self::assertInstanceOf(QualifiedName::class, $name);
        self::assertSame(['t', 'a'], [$name->name->value, $name->schema?->value]);
    }

    public function testModifiedRefusesChangingASequenceOrAMaterializedView(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE SEQUENCE s'), $semantics->analyze('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a'), $semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['cannot change sequence "s"'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('UPDATE s SET log_cnt = 0', $context)->facts->diagnostics));
        self::assertSame(['cannot change materialized view "m"'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('DELETE FROM m', $context)->facts->diagnostics));
        self::assertSame([], $semantics->analyze('INSERT INTO v VALUES (1)', $context)->facts->diagnostics);
    }

    public function testModifiedAcceptsMergeIntoAViewFromPostgreSql17(): void
    {
        $sql = 'MERGE INTO v USING (SELECT 1 AS b) x ON true WHEN MATCHED THEN DELETE';
        $old = new Semantics(Dialect::PostgreSql, 'pg-16.6');
        $new = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        self::assertSame(['cannot execute MERGE on relation "v"'], array_map(static fn ($problem): string => $problem->message(), $old->analyze($sql, [$old->analyze('CREATE VIEW v AS SELECT 1 AS a')])->facts->diagnostics));
        self::assertSame([], $new->analyze($sql, [$new->analyze('CREATE VIEW v AS SELECT 1 AS a')])->facts->diagnostics);
    }

    public function testForeignRefusesKeysOfAForeignTable(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame([
            'primary key constraints are not supported on foreign tables',
            'exclusion constraints are not supported on foreign tables',
        ], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE FOREIGN TABLE f (a int PRIMARY KEY, b int CHECK (b > 0), EXCLUDE (a WITH =)) SERVER x')->facts->diagnostics));
    }

    public function testTriggeredRefusesTriggersTheKindCannotHave(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE t (a int)'), $semantics->analyze('CREATE VIEW v AS SELECT 1 AS a'), $semantics->analyze('CREATE SEQUENCE s')];
        self::assertSame(['"t" is a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE TRIGGER g INSTEAD OF INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()', $context)->facts->diagnostics));
        self::assertSame(['"v" is a view'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE TRIGGER g BEFORE INSERT ON v FOR EACH ROW EXECUTE FUNCTION f()', $context)->facts->diagnostics));
        self::assertSame(['relation "s" cannot have triggers'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE TRIGGER g AFTER INSERT ON s EXECUTE FUNCTION f()', $context)->facts->diagnostics));
        self::assertSame([], $semantics->analyze('CREATE TRIGGER g INSTEAD OF INSERT ON v FOR EACH ROW EXECUTE FUNCTION f()', $context)->facts->diagnostics);
    }
}
