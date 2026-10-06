<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(Update::class)]
#[Medium]
final class UpdateTest extends TestCase
{
    public function testInputAnswersTheFromRelation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $joined = $semantics->analyze('UPDATE t SET a = u.a FROM u');
        $plain = $semantics->analyze('UPDATE t SET a = 1');

        self::assertInstanceOf(Update::class, $joined->statement);
        self::assertInstanceOf(TableInput::class, $joined->statement->input());
        self::assertSame('u', $joined->singleNamedInput()->name()->name->value);
        self::assertInstanceOf(Update::class, $plain->statement);
        self::assertNull($plain->statement->input());
        self::assertNull($plain->inputRelation());
    }

    public function testDeriveWithinSeesTheTargetAndTheFromTablesInAssignmentsAndWhere(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $query = $semantics->analyze('UPDATE t SET a = u.a FROM u WHERE u.c = t.b', [$t, $u]);

        self::assertInstanceOf(Update::class, $query->statement);
        self::assertInstanceOf(Assignment::class, $query->statement->assignments[0]);
        $value = $query->facts->scalar($query->statement->assignments[0]->value)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $value);
        self::assertSame($query->statement->from, $value->relation);
        self::assertSame($u->declarations()[0]->columns[0], $value->declaration());
        self::assertInstanceOf(Binary::class, $query->statement->where);
        $right = $query->facts->scalar($query->statement->where->right)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $right);
        self::assertSame($query->statement->target, $right->relation);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveWithinLetsReturningSeeTheTargetOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $star = $semantics->analyze('UPDATE t SET a = u.a FROM u WHERE u.a = t.a RETURNING *', [$t, $u]);
        $foreign = $semantics->analyze('UPDATE t SET a = u.a FROM u WHERE u.a = t.a RETURNING u.c', [$t, $u]);

        self::assertSame(['id', 'a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $star->fields()->items ?? []));
        self::assertSame($t->declarations()[0]->columns[0], $star->field('id')->column());
        self::assertInstanceOf(MissingColumn::class, $foreign->field(0)->resolution);
        self::assertInstanceOf(MissingColumn::class, $foreign->facts->diagnostics[0]);
    }

    public function testDeriveStatementReportsAnAssignmentToAColumnTheTableLacks(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('UPDATE t SET zz = 1', [$t]);

        self::assertInstanceOf(MissingColumn::class, $query->facts->diagnostics[0]);
        self::assertSame('zz', $query->facts->diagnostics[0]->name->value);
        self::assertSame([], $semantics->analyze('UPDATE t SET zz = 1')->facts->diagnostics);
    }

    public function testDeriveWithinAnswersNullWithoutReturning(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->analyze('UPDATE t SET a = 1 WHERE b IS NULL', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));

        self::assertInstanceOf(Update::class, $statement);
        self::assertNull($statement->deriveWithin($derivation, $derivation->environment()));
        self::assertNotNull($statement->where);
        self::assertTrue($derivation->facts()->covers($statement->where));
    }

    public function testDeriveStatementReportsRaiseOutsideATrigger(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('UPDATE t SET a = RAISE(IGNORE)');

        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::RaiseOutsideTrigger, $query->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesEveryClauseInGrammarOrder(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("with w as (select 1 as q) update or fail main.t indexed by i set a = 1, (b, a) = ('x', 2) from u, w where u.a = t.a returning id");

        self::assertSame("WITH w AS (SELECT 1 AS q) UPDATE OR FAIL main.t INDEXED BY i SET a = 1, (b, a) = ('x', 2) FROM u, w WHERE u.a = t.a RETURNING id", $query->toString());
    }

    public function testRejectsAnUpdateWithoutAssignments(): void
    {
        $this->expectExceptionMessage('An UPDATE has at least one assignment of a column or of a column row.');

        new Update(new MutationTarget(new QualifiedName(new Name('t'))), []);
    }
}
