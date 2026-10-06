<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JoinUsing::class)]
#[Medium]
final class JoinUsingTest extends TestCase
{
    public function testRenderWritesTheColumnsInParentheses(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $step = new JoinStep(new JoinOperator(), new TableInput(new QualifiedName(new Name('u'))), new JoinUsing([new Name('a'), new Name('b')]));
        $built = new Operation($semantics->context(), new Select([new Star()], new JoinChain(new TableInput(new QualifiedName(new Name('t'))), [$step])));
        $query = $semantics->analyze('select * from t join u using(a,b)');

        self::assertSame('SELECT * FROM t JOIN u USING (a, b)', $built->toString());
        self::assertSame('SELECT * FROM t JOIN u USING (a, b)', $query->toString());
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(JoinChain::class, $query->statement->from);
        self::assertInstanceOf(JoinUsing::class, $query->statement->from->steps[0]->constraint);
        self::assertSame(['a', 'b'], array_map(static fn (Name $name): string => $name->value, $query->statement->from->steps[0]->constraint->columns));
    }

    public function testColumnsResolveToTheLeftSideUnlessQualified(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $query = $semantics->analyze('SELECT a, u.a FROM t LEFT JOIN u USING (a)', [$t, $u]);
        $bare = $query->field(0)->resolution;
        $qualified = $query->field(1)->resolution;

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(JoinChain::class, $query->statement->from);
        self::assertInstanceOf(ResolvedColumn::class, $bare);
        self::assertInstanceOf(ResolvedColumn::class, $qualified);
        self::assertSame($query->statement->from->first, $bare->relation);
        self::assertSame($t->declarations()[0]->columns[1], $bare->declaration());
        self::assertSame($query->statement->from->steps[0]->relation, $qualified->relation);
        self::assertSame($u->declarations()[0]->columns[0], $qualified->declaration());
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testColumnsMustBePresentOnBothSidesWhenTheyAreKnown(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $closed = $semantics->analyze('SELECT * FROM t JOIN u USING (zz)', [$t, $u]);
        $open = $semantics->analyze('SELECT * FROM t JOIN u USING (zz)');

        self::assertInstanceOf(MissingColumn::class, $closed->facts->diagnostics[0]);
        self::assertSame('zz', $closed->facts->diagnostics[0]->name->value);
        self::assertCount(5, $closed->fields()->items ?? []);
        self::assertSame([], $open->facts->diagnostics);
    }

    public function testRejectsAnEmptyColumnList(): void
    {
        $this->expectExceptionMessage('USING names at least one column.');

        new JoinUsing([]);
    }
}
