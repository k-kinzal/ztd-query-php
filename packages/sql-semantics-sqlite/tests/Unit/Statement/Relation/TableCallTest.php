<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\DependentField;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableCall;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TableCall::class)]
#[Medium]
final class TableCallTest extends TestCase
{
    public function testDeriveRelationIsAnOpenShapeNamingTheUndeclaredRoutine(): void
    {
        $semantics = new Inspection();
        $t = $semantics->inspect('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->inspect('SELECT * FROM json_each(1) AS j', [$t]);
        $shape = $query->shape();

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(TableCall::class, $query->statement->from);
        self::assertSame('json_each', $query->statement->from->name->name->value);
        self::assertSame('j', $query->statement->from->alias?->value);
        self::assertNull($query->fields());
        self::assertNotNull($shape);
        self::assertSame([], $shape->slots);
        self::assertInstanceOf(UndeclaredRoutine::class, $shape->missing[0]);
        self::assertSame('json_each', $shape->missing[0]->name->name->value);
        self::assertNull($query->facts->relation($query->statement->from)->table);
        self::assertInstanceOf(DependentField::class, $query->lookupField('value'));
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveRelationResolvesArgumentsAgainstTheTablesToTheLeft(): void
    {
        $semantics = new Inspection();
        $t = $semantics->inspect('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->inspect('SELECT * FROM t, json_each(b) AS j', [$t]);

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(JoinChain::class, $query->statement->from);
        $call = $query->statement->from->steps[0]->relation;
        self::assertInstanceOf(TableCall::class, $call);
        $resolution = $query->facts->scalar($call->arguments[0])->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($query->statement->from->first, $resolution->relation);
        self::assertSame($t->declarations()[0]->columns[2], $resolution->declaration());
        self::assertNull($query->fields());
    }

    public function testRenderWritesSchemaNameArgumentsAndAlias(): void
    {
        $semantics = new Inspection();
        $call = new TableCall(new QualifiedName(new Name('json_each'), new Name('main')), [new IntegerLiteral('1'), new TextLiteral('x')], new Name('j'));
        $built = new InspectedStatement($semantics->context(), new Select([new Star()], $call));

        self::assertSame("SELECT * FROM main.json_each(1, 'x') AS j", $built->toString());
        self::assertSame('SELECT * FROM generate_series(1, 3)', $semantics->inspect('select * from generate_series(1,3)')->toString());
        self::assertSame('SELECT * FROM pragma_table_info() p', $semantics->inspect('SELECT * FROM pragma_table_info() p')->toString());
        self::assertSame('SELECT * FROM pragma_table_info() AS p', $semantics->inspect('SELECT * FROM pragma_table_info() as p')->toString());
    }

    public function testRenderRefusesToLeaveOutAsWithoutAnAlias(): void
    {
        $this->expectExceptionMessage('AS is left out only before an alias.');

        new TableCall(new QualifiedName(new Name('f')), [], null, false);
    }
}
