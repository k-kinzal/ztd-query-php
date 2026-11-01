<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableCall;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Shape\DependentField;

#[CoversClass(TableCall::class)]
#[Medium]
final class TableCallTest extends TestCase
{
    public function testDeriveRelationIsAnOpenShapeNamingTheUndeclaredRoutine(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT * FROM json_each(1) AS j', [$t]);
        $shape = $query->shape();

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(TableCall::class, $query->from);
        self::assertSame('json_each', $query->from->name->name->value);
        self::assertSame('j', $query->from->alias?->value);
        self::assertNull($query->fields());
        self::assertNotNull($shape);
        self::assertSame([], $shape->slots);
        self::assertInstanceOf(UndeclaredRoutine::class, $shape->missing[0]);
        self::assertSame('json_each', $shape->missing[0]->name->name->value);
        self::assertNull($query->from->relationInformation()->table);
        self::assertInstanceOf(DependentField::class, $query->lookupField('value'));
        self::assertSame([], $query->diagnostics());
    }

    public function testDeriveRelationResolvesArgumentsAgainstTheTablesToTheLeft(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT * FROM t, json_each(b) AS j', [$t]);

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(JoinChain::class, $query->from);
        $call = $query->from->steps[0]->relation;
        self::assertInstanceOf(TableCall::class, $call);
        $resolution = $call->arguments[0]->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($query->from->first, $resolution->relation);
        self::assertSame($t->declarations()[0]->columns[2], $resolution->declaration());
        self::assertNull($query->fields());
    }

    public function testRenderWritesSchemaNameArgumentsAndAlias(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $call = new TableCall(new QualifiedName(new Name('json_each'), new Name('main')), [new IntegerLiteral('1'), new TextLiteral('x')], new Name('j'));
        $built = Semantics::fromStatement($semantics->context(), new Select([new Star()], $call));

        self::assertSame("SELECT * FROM main.json_each(1, 'x') AS j", $built->toString());
        self::assertSame('SELECT * FROM generate_series(1, 3)', $semantics->analyze('select * from generate_series(1,3)')->toString());
        self::assertSame('SELECT * FROM pragma_table_info() p', $semantics->analyze('SELECT * FROM pragma_table_info() p')->toString());
        self::assertSame('SELECT * FROM pragma_table_info() AS p', $semantics->analyze('SELECT * FROM pragma_table_info() as p')->toString());
    }

    public function testRenderRefusesToLeaveOutAsWithoutAnAlias(): void
    {
        $this->expectExceptionMessage('AS is left out only before an alias.');

        new TableCall(new QualifiedName(new Name('f')), [], null, false);
    }
}
