<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TriggerTable::class)]
#[Medium]
final class TriggerTableTest extends TestCase
{
    public function testDeriveRelationAnswersTheRowOfTheDeclaredTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $create = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.a = NEW.b', [$table]);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        $fact = $create->facts->relation($statement->table);
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertInstanceOf(Known::class, $fact->shape->slots[1]->type ?? null);

        self::assertSame($table, $fact->table->table);
        self::assertTrue($fact->shape->complete());
        self::assertCount(2, $fact->shape->slots);
        self::assertSame($table->columns[0], $fact->shape->slots[0]->column);
        self::assertSame(Nullability::NotNull, $fact->shape->slots[0]->nullability);
        self::assertSame($table->columns[1], $fact->shape->slots[1]->column);
        self::assertSame($table->columns[1]->type, $fact->shape->slots[1]->type->descriptor);
        self::assertSame(Nullability::Nullable, $fact->shape->slots[1]->nullability);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveRelationReportsATableTheCompleteContextLacks(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @x = 1', []);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        $fact = $create->facts->relation($statement->table);
        self::assertInstanceOf(MissingTable::class, $fact->table);

        self::assertSame('t', $fact->table->name->name->value);
        self::assertTrue($fact->shape->complete());
        self::assertSame([], $fact->shape->slots);
        self::assertSame(['Relation t does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveRelationDoesNotFindATableOfAnotherDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $create = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON shop.t FOR EACH ROW SET @x = 1', [$table]);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);

        $resolution = $create->facts->relation($statement->table)->table;
        self::assertInstanceOf(MissingTable::class, $resolution);

        self::assertSame('shop', $resolution->name->schema?->value);
        self::assertSame(['Relation t does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveRelationLeavesTheShapeOpenForAnUndeclaredTable(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TRIGGER tr BEFORE INSERT ON shop.t FOR EACH ROW SET @x = 1');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        $fact = $create->facts->relation($statement->table);
        self::assertInstanceOf(UndeclaredTable::class, $fact->table);

        self::assertFalse($fact->shape->complete());
        self::assertSame([], $fact->shape->slots);
        self::assertSame('the declaration of relation shop.t', $fact->shape->missing[0]->describe());
        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheTableName(): iterable
    {
        yield 'MySQL 5.6 quoted names' => [
            'mysql-5.6.51',
            'create trigger tr before insert on `select`.`a b` for each row set @a = 1',
            'CREATE TRIGGER tr BEFORE INSERT ON `select`.`a b` FOR EACH ROW SET @a = 1',
        ];
        yield 'MySQL 5.7 RANK is not reserved' => ['mysql-5.7.44', 'create trigger tr before insert on rank for each row set @a = 1', 'CREATE TRIGGER tr BEFORE INSERT ON rank FOR EACH ROW SET @a = 1'];
        yield 'MySQL 8.0 RANK is reserved' => ['mysql-8.0.44', 'create trigger tr before insert on `rank` for each row set @a = 1', 'CREATE TRIGGER tr BEFORE INSERT ON `rank` FOR EACH ROW SET @a = 1'];
        yield 'MySQL 9.1 qualified name' => ['mysql-9.1.0', 'create trigger tr after delete on shop.t for each row set @a = 1', 'CREATE TRIGGER tr AFTER DELETE ON shop.t FOR EACH ROW SET @a = 1'];
    }

    #[DataProvider('providerRenderWritesTheTableName')]
    public function testRenderWritesTheTableName(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedTable(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new TriggerTable(new QualifiedName(new Name('t'), new Name('shop'))))->render($out);

        self::assertSame('shop.t', (new Lexical())->join($out->pieces()));
    }

    public function testANameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('A table name has at most a database qualifier.');

        new TriggerTable(new QualifiedName(new Name('t'), new Name('shop'), new Name('def')));
    }
}
