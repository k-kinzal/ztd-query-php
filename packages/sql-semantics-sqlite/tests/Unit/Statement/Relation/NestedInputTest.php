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
use SqlSemantics\Platform\Sqlite\Statement\Relation\NestedInput;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(NestedInput::class)]
#[Medium]
final class NestedInputTest extends TestCase
{
    public function testDeriveRelationExposesTheJoinWrittenInParentheses(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $query = $semantics->analyze('SELECT * FROM (t JOIN u USING (a))', [$t, $u]);
        $fields = $query->fields();

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(NestedInput::class, $query->statement->from);
        self::assertInstanceOf(JoinChain::class, $query->statement->from->relation);
        self::assertNull($query->statement->from->alias);
        self::assertNotNull($fields);
        self::assertSame(['id', 'a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertCount(4, $query->facts->relation($query->statement->from)->shape->slots);
        self::assertCount(4, $query->facts->relation($query->statement->from->relation)->shape->slots);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveRelationRenamesASingleTableByTheAlias(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $aliased = $semantics->analyze('SELECT x.a FROM (t) AS x', [$t]);
        $original = $semantics->analyze('SELECT t.a FROM (t) AS x', [$t]);
        $resolution = $aliased->field(0)->resolution;

        self::assertInstanceOf(Select::class, $aliased->statement);
        self::assertInstanceOf(NestedInput::class, $aliased->statement->from);
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($aliased->statement->from->relation, $resolution->relation);
        self::assertSame($t->declarations()[0]->columns[1], $resolution->declaration());
        self::assertInstanceOf(MissingColumn::class, $original->field(0)->resolution);
    }

    public function testDeriveRelationQualifiesAJoinedPairByTheAlias(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $qualified = $semantics->analyze('SELECT j.a, j.c FROM (t JOIN u USING (a)) AS j', [$t, $u]);
        $ambiguous = $semantics->analyze('SELECT a FROM (t JOIN u) AS j', [$t, $u]);

        self::assertSame($t->declarations()[0]->columns[1], $qualified->field(0)->column());
        self::assertSame($u->declarations()[0]->columns[1], $qualified->field(1)->column());
        self::assertSame([], $qualified->facts->diagnostics);
        self::assertInstanceOf(AmbiguousColumn::class, $ambiguous->field(0)->resolution);
    }

    public function testRenderWritesParenthesesAndTheAlias(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $built = new Operation($semantics->context(), new Select([new Star()], new NestedInput(new TableInput(new QualifiedName(new Name('t'))), new Name('x'))));

        self::assertSame('SELECT * FROM (t) AS x', $built->toString());
        self::assertSame('SELECT * FROM (t JOIN u USING (a)) j, u u2', $semantics->analyze('select * from (t join u using (a)) j, u u2')->toString());
        self::assertSame('SELECT * FROM ((t))', $semantics->analyze('SELECT * FROM ((t))')->toString());
    }

    public function testRenderRefusesToLeaveOutAsWithoutAnAlias(): void
    {
        $this->expectExceptionMessage('AS is left out only before an alias.');

        new NestedInput(new TableInput(new QualifiedName(new Name('t'))), null, false);
    }
}
