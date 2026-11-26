<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TableInput::class)]
#[Medium]
final class TableInputTest extends TestCase
{
    public function testNameAndAliasAnswerTheWrittenNames(): void
    {
        $query = (new Inspection())->diagnose('SELECT * FROM main.t AS x INDEXED BY i');
        $input = $query->singleNamedInput();

        self::assertInstanceOf(TableInput::class, $input);
        self::assertSame('t', $input->name()->name->value);
        self::assertSame('main', $input->name()->schema?->value);
        self::assertSame('x', $input->alias()?->value);
        self::assertSame('i', $input->index?->index?->value);
        self::assertNull((new Inspection())->diagnose('SELECT * FROM t')->singleNamedInput()->alias());
    }

    public function testDeriveRelationResolvesADeclaredTableWithItsRowIdentifier(): void
    {
        $semantics = new Inspection();
        $t = $semantics->diagnose('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->diagnose('SELECT b, rowid FROM t', [$t]);
        $fact = $query->facts->relation($query->singleNamedInput());

        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($t->declarations()[0], $fact->table->table);
        self::assertCount(3, $fact->shape->slots);
        self::assertTrue($fact->shape->complete());
        self::assertSame($t->declarations()[0]->columns[2], $query->field('b')->column());
        self::assertSame($t->declarations()[0]->columns[0], $query->field(1)->column());
        self::assertSame(Nullability::Nullable, $query->field('b')->nullability);
    }

    public function testDeriveRelationResolvesACommonTableToItsDefinition(): void
    {
        $semantics = new Inspection();
        $t = $semantics->diagnose('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->diagnose('WITH w AS (SELECT a FROM t) SELECT * FROM w', [$t]);

        self::assertInstanceOf(WithQuery::class, $query->statement);
        self::assertInstanceOf(Select::class, $query->statement->body);
        self::assertInstanceOf(TableInput::class, $query->statement->body->from);
        $fact = $query->facts->relation($query->statement->body->from);
        self::assertInstanceOf(CommonTable::class, $fact->table);
        self::assertSame($query->statement->with->tables[0], $fact->table->definition);
        self::assertSame($t->declarations()[0]->columns[1], $query->field('a')->column());
    }

    public function testDeriveRelationAnswersMissingAndUndeclaredTables(): void
    {
        $semantics = new Inspection();
        $missing = $semantics->diagnose('SELECT * FROM t', []);
        $undeclared = $semantics->diagnose('SELECT * FROM t');

        self::assertInstanceOf(MissingTable::class, $missing->facts->relation($missing->singleNamedInput())->table);
        self::assertSame($missing->facts->relation($missing->singleNamedInput())->table, $missing->facts->diagnostics[0]);
        self::assertInstanceOf(UndeclaredTable::class, $undeclared->facts->relation($undeclared->singleNamedInput())->table);
        self::assertFalse($undeclared->facts->relation($undeclared->singleNamedInput())->shape->complete());
        self::assertSame([], $undeclared->facts->diagnostics);
    }

    public function testAliasHidesTheTableName(): void
    {
        $semantics = new Inspection();
        $t = $semantics->diagnose('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $aliased = $semantics->diagnose('SELECT x.a FROM t AS x', [$t]);
        $original = $semantics->diagnose('SELECT t.a FROM t AS x', [$t]);

        self::assertSame($t->declarations()[0]->columns[1], $aliased->field(0)->column());
        self::assertInstanceOf(MissingColumn::class, $original->field(0)->resolution);
        self::assertSame('t', $original->field(0)->resolution->qualifier?->name->value);
    }

    public function testRenderWritesSchemaAliasAndIndexChoice(): void
    {
        $semantics = new Inspection();
        $built = new InspectedStatement($semantics->context(), new Select([new Star()], new TableInput(new QualifiedName(new Name('t'), new Name('main')), new Name('x'), new IndexChoice())));

        self::assertSame('SELECT * FROM main.t AS x NOT INDEXED', $built->toString());
        self::assertSame('SELECT * FROM t x INDEXED BY i', $semantics->diagnose('select * from t x indexed by i')->toString());
        self::assertSame('SELECT * FROM t AS x INDEXED BY i', $semantics->diagnose('select * from t as x indexed by i')->toString());
    }

    public function testRenderRefusesToLeaveOutAsWithoutAnAlias(): void
    {
        $this->expectExceptionMessage('AS is left out only before an alias.');

        new TableInput(new QualifiedName(new Name('t')), null, null, false);
    }
}
