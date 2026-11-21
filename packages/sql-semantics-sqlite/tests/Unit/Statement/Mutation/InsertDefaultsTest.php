<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;

#[CoversClass(InsertDefaults::class)]
#[Medium]
final class InsertDefaultsTest extends TestCase
{
    public function testDeriveStatementReturnsTheRowOfTheTarget(): void
    {
        $semantics = new Semantics();
        $t = $semantics->inspect('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $star = $semantics->inspect('INSERT INTO t DEFAULT VALUES RETURNING *', [$t]);
        $plain = $semantics->inspect('INSERT INTO t DEFAULT VALUES', [$t]);
        $fields = $star->fields();

        self::assertInstanceOf(InsertDefaults::class, $star->statement);
        self::assertInstanceOf(DeclaredTable::class, $star->facts->relation($star->statement->into->target)->table);
        self::assertNotNull($fields);
        self::assertSame(['id', 'a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame($t->declarations()[0]->columns[1], $star->field('a')->column());
        self::assertSame(Nullability::NotNull, $star->field('a')->nullability);
        self::assertSame([], $star->facts->diagnostics);
        self::assertNull($plain->shape());
    }

    public function testDeriveStatementReportsAColumnTheTableLacks(): void
    {
        $semantics = new Semantics();
        $t = $semantics->inspect('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->inspect('INSERT INTO t (zz) DEFAULT VALUES', [$t]);

        self::assertInstanceOf(MissingColumn::class, $query->facts->diagnostics[0]);
        self::assertSame('zz', $query->facts->diagnostics[0]->name->value);
        self::assertSame([], $semantics->inspect('INSERT INTO t (zz) DEFAULT VALUES')->facts->diagnostics);
    }

    public function testDeriveWithinAnswersNullWithoutReturning(): void
    {
        $semantics = new Semantics();
        $t = $semantics->inspect('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->inspect('INSERT INTO t DEFAULT VALUES', [$t])->statement;
        $returning = $semantics->inspect('INSERT INTO t DEFAULT VALUES RETURNING id', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));

        self::assertInstanceOf(InsertDefaults::class, $statement);
        self::assertInstanceOf(InsertDefaults::class, $returning);
        self::assertNull($statement->deriveWithin($derivation, $derivation->environment()));
        self::assertSame('id', $returning->deriveWithin($derivation, $derivation->environment())?->shape->slots[0]->name?->value);
    }

    public function testRenderWritesDefaultValuesAndReturning(): void
    {
        $semantics = new Semantics();
        $built = new Operation($semantics->context(), new InsertDefaults(new InsertInto(new MutationTarget(new QualifiedName(new Name('t')))), [new Star()]));

        self::assertSame('INSERT INTO t DEFAULT VALUES RETURNING *', $built->toString());
        self::assertSame('REPLACE INTO main.t (a) DEFAULT VALUES', $semantics->inspect('replace into main.t (a) default values')->toString());
    }
}
