<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterAddColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationObstacle;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationRefused;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(AlterAddColumn::class)]
#[Medium]
final class AlterAddColumnTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndProvidesNoDeclaration(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $operation = $semantics->analyze('ALTER TABLE t ADD COLUMN b TEXT', [$table]);
        $resolution = $operation->facts->relation($operation->statement)->table;

        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
        self::assertSame([], $operation->declarations());
        self::assertCount(1, $table->declarations()[0]->columns);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveStatementDerivesTheConstraintsAgainstTheTableWithTheNewColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $operation = $semantics->analyze('ALTER TABLE t ADD b TEXT CHECK (b <> a)', [$table]);
        $statement = $operation->statement;

        self::assertInstanceOf(AlterAddColumn::class, $statement);
        $check = $statement->column->constraints[0];
        self::assertInstanceOf(ColumnCheck::class, $check);
        self::assertInstanceOf(Binary::class, $check->expression);
        $existing = $operation->facts->scalar($check->expression->right)->resolution;
        $added = $operation->facts->scalar($check->expression->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $existing);
        self::assertInstanceOf(ResolvedColumn::class, $added);
        self::assertSame($table->declarations()[0]->columns[0], $existing->slot->column);
        self::assertNull($added->slot->column);
        $type = $operation->facts->relation($statement)->shape->slots[1]->type;
        self::assertInstanceOf(Known::class, $type);
        self::assertSame('TEXT', $type->descriptor->name());
    }

    public function testDeriveStatementReportsWhatRulesTheAdditionOut(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $duplicate = $semantics->analyze('ALTER TABLE t ADD A TEXT', [$table]);
        $key = $semantics->analyze('ALTER TABLE t ADD b INTEGER PRIMARY KEY', [$table]);
        $missing = $semantics->analyze('ALTER TABLE u ADD b', [$table]);

        self::assertInstanceOf(DuplicateColumn::class, $duplicate->facts->diagnostics[0]);
        self::assertInstanceOf(AlterationRefused::class, $key->facts->diagnostics[0]);
        self::assertSame(AlterationObstacle::PrimaryKeyColumn, $key->facts->diagnostics[0]->obstacle);
        self::assertInstanceOf(MissingTable::class, $missing->facts->diagnostics[0]);
    }

    public function testDeriveRelationAppendsTheNewColumnToTheShapeOfTheTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $statement = $semantics->analyze('ALTER TABLE t ADD b TEXT NOT NULL DEFAULT 0')->statement;
        $derivation = new Derivation($semantics->context([$table]));

        self::assertInstanceOf(AlterAddColumn::class, $statement);
        $fact = $statement->deriveRelation($derivation, $derivation->environment());
        self::assertSame(['a', 'b'], array_map(static fn (object $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertSame(Nullability::NotNull, $fact->shape->slots[1]->nullability);
        self::assertTrue($fact->shape->complete());
    }

    public function testRenderDropsTheOptionalKeyword(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('alter table main.t add column b text default 0');

        self::assertSame('ALTER TABLE main.t ADD b text DEFAULT 0', $operation->toString());
    }
}
