<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(TriggerTable::class)]
#[Medium]
final class TriggerTableTest extends TestCase
{
    public function testNameAnswersTheWatchedTableAndAliasIsAlwaysNull(): void
    {
        $trigger = (new Semantics(Dialect::Sqlite))->analyze('CREATE TRIGGER tr INSERT ON main.t BEGIN SELECT 1; END');

        self::assertInstanceOf(CreateTrigger::class, $trigger->statement);
        self::assertSame('t', $trigger->statement->table->name()->name->value);
        self::assertSame('main', $trigger->statement->table->name()->schema?->value);
        self::assertNull($trigger->statement->table->alias());
    }

    public function testDeriveRelationResolvesTheWatchedTableInTheContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $declared = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT new.a; END', [$t]);
        $missing = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; END', []);
        $undeclared = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT new.a; END');

        self::assertInstanceOf(CreateTrigger::class, $declared->statement);
        $fact = $declared->facts->relation($declared->statement->table);
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($t->declarations()[0], $fact->table->table);
        self::assertCount(3, $fact->shape->slots);
        self::assertInstanceOf(CreateTrigger::class, $missing->statement);
        self::assertInstanceOf(MissingTable::class, $missing->facts->relation($missing->statement->table)->table);
        self::assertInstanceOf(MissingTable::class, $missing->facts->diagnostics[0]);
        self::assertInstanceOf(CreateTrigger::class, $undeclared->statement);
        self::assertInstanceOf(UndeclaredTable::class, $undeclared->facts->relation($undeclared->statement->table)->table);
        self::assertSame([], $undeclared->facts->diagnostics);
    }

    public function testRenderWritesTheSchemaAndTheName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $built = new Operation($semantics->context(), new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'), new Name('temp'))), [new Select([new ResultColumn(new IntegerLiteral('1'))])]));

        self::assertSame('CREATE TRIGGER tr INSERT ON `temp`.t BEGIN SELECT 1; END', $built->toString());
        self::assertSame('CREATE TRIGGER tr INSERT ON main.t BEGIN SELECT 1; END', $semantics->analyze('create trigger tr insert on main.t begin select 1; end')->toString());
    }
}
