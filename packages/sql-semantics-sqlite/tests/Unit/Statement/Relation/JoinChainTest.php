<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JoinChain::class)]
#[Medium]
final class JoinChainTest extends TestCase
{
    public function testDeriveRelationShowsAMergedColumnOnce(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $using = $semantics->analyze('SELECT * FROM t JOIN u USING (a)', [$t, $u]);
        $natural = $semantics->analyze('SELECT * FROM t NATURAL JOIN u', [$t, $u]);
        $fields = $using->fields();

        self::assertInstanceOf(Select::class, $using->statement);
        self::assertInstanceOf(JoinChain::class, $using->statement->from);
        self::assertNotNull($fields);
        self::assertSame(['id', 'a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame(['id', 'a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $natural->fields()->items ?? []));
        self::assertSame($t->declarations()[0]->columns[1], $using->field('a')->column());
        self::assertNull($using->facts->relation($using->statement->from)->table);
        self::assertCount(4, $using->facts->relation($using->statement->from)->shape->slots);
        self::assertSame([], $using->facts->diagnostics);
    }

    public function testDeriveRelationMakesTheExtendedSideNullable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $query = $semantics->analyze('SELECT * FROM t LEFT JOIN u ON t.a = u.a', [$t, $u]);
        $fields = $query->fields();

        self::assertNotNull($fields);
        self::assertSame(['id', 'a', 'b', 'a', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $query->field(3)->nullability);
        self::assertSame($u->declarations()[0]->columns[0], $query->field(3)->column());
    }

    public function testDeriveRelationCoalescesTheMergedColumnOfARightOrFullJoin(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $right = $semantics->analyze('SELECT * FROM t RIGHT JOIN u USING (a)', [$t, $u]);
        $full = $semantics->analyze('SELECT * FROM t FULL OUTER JOIN u USING (a)', [$t, $u]);

        self::assertSame(['id', 'a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $right->fields()->items ?? []));
        self::assertInstanceOf(Choice::class, $right->field('a')->type);
        self::assertSame(Nullability::Nullable, $right->field('id')->nullability);
        self::assertSame(Nullability::Nullable, $right->field('b')->nullability);
        self::assertSame(Nullability::Nullable, $right->field('c')->nullability);
        self::assertSame($t->declarations()[0]->columns[1], $right->field('a')->column());
        self::assertInstanceOf(Choice::class, $full->field('a')->type);
        self::assertSame(Nullability::Nullable, $full->field('c')->nullability);
    }

    public function testDeriveRelationReportsAConstraintWrittenWithoutAJoin(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $on = $semantics->analyze('SELECT * FROM t ON 1', [$t]);
        $using = $semantics->analyze('SELECT * FROM t USING (a)', [$t]);

        self::assertInstanceOf(Select::class, $on->statement);
        self::assertInstanceOf(JoinChain::class, $on->statement->from);
        self::assertInstanceOf(JoinOn::class, $on->statement->from->constraint);
        self::assertSame([], $on->statement->from->steps);
        self::assertInstanceOf(Misuse::class, $on->facts->diagnostics[0]);
        self::assertSame(MisuseRule::OnWithoutJoin, $on->facts->diagnostics[0]->rule);
        self::assertInstanceOf(Misuse::class, $using->facts->diagnostics[0]);
        self::assertSame(MisuseRule::UsingWithoutJoin, $using->facts->diagnostics[0]->rule);
        self::assertCount(3, $on->fields()->items ?? []);
    }

    public function testDeriveRelationReportsAnUnknownJoinTypeAndANaturalJoinWithAConstraint(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $unknown = $semantics->analyze('SELECT * FROM t INNER LEFT JOIN u', [$t, $u]);
        $named = $semantics->analyze('SELECT * FROM t LEFT WRONG JOIN u', [$t, $u]);
        $natural = $semantics->analyze('SELECT * FROM t NATURAL JOIN u ON 1', [$t, $u]);

        self::assertInstanceOf(Misuse::class, $unknown->facts->diagnostics[0]);
        self::assertSame(MisuseRule::UnknownJoinType, $unknown->facts->diagnostics[0]->rule);
        self::assertCount(5, $unknown->fields()->items ?? []);
        self::assertInstanceOf(Misuse::class, $named->facts->diagnostics[0]);
        self::assertSame(MisuseRule::UnknownJoinType, $named->facts->diagnostics[0]->rule);
        self::assertInstanceOf(Misuse::class, $natural->facts->diagnostics[0]);
        self::assertSame(MisuseRule::NaturalJoinWithConstraint, $natural->facts->diagnostics[0]->rule);
        self::assertCount(1, $natural->facts->diagnostics);
    }

    public function testDeriveRelationListsCommaSeparatedTablesSideBySide(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $query = $semantics->analyze('SELECT * FROM t, u', [$t, $u]);

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(JoinChain::class, $query->statement->from);
        self::assertTrue($query->statement->from->steps[0]->operator->comma);
        self::assertSame(['id', 'a', 'b', 'a', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $query->fields()->items ?? []));
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveRelationLeavesTheShapeOpenForUndeclaredTables(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t JOIN u USING (a)');
        $shape = $query->shape();

        self::assertNull($query->fields());
        self::assertNotNull($shape);
        self::assertSame([], $shape->slots);
        self::assertSame(['the declaration of relation t', 'the declaration of relation u'], array_map(static fn (object $missing): string => $missing->describe(), $shape->missing));
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testRenderWritesTheFirstTermItsConstraintAndEachStep(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = new TableInput(new QualifiedName(new Name('t')));
        $u = new TableInput(new QualifiedName(new Name('u')));
        $built = new Operation($semantics->context(), new Select([new Star()], new JoinChain($t, [new JoinStep(new JoinOperator(true), $u, new JoinUsing([new Name('a')]))])));

        self::assertSame('SELECT * FROM t, u USING (a)', $built->toString());
        self::assertSame('SELECT * FROM t LEFT OUTER JOIN u ON t.a = u.a CROSS JOIN t AS t2', $semantics->analyze('select * from t left outer join u on t.a = u.a cross join t t2')->toString());
        self::assertSame('SELECT * FROM t ON 1', $semantics->analyze('SELECT * FROM t ON 1')->toString());
    }

    public function testRejectsASingleTermWithoutAConstraint(): void
    {
        $this->expectExceptionMessage('A single term without a constraint is no join chain.');

        new JoinChain(new TableInput(new QualifiedName(new Name('t'))), []);
    }

    public function testRejectsAChainAsTheFirstTerm(): void
    {
        $t = new TableInput(new QualifiedName(new Name('t')));
        $chain = new JoinChain($t, [new JoinStep(new JoinOperator(), new TableInput(new QualifiedName(new Name('u'))))]);

        $this->expectExceptionMessage('A join chain used as a term is written in parentheses.');

        new JoinChain($chain, []);
    }
}
