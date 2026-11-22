<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(SourceBinding::class)]
#[Small]
final class SourceBindingTest extends TestCase
{
    public function testReferenceAndSourceKeepTheBoundColumnName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT a FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $result);

        $binding = new SourceBinding($operation->binding(), $select->from);
        self::assertSame(0, $binding->reference($result->expression)->slot);
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }

    public function testSourceIsOptional(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->source());
    }

    public function testSlotIsStableWithinTheOccurrence(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT a FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $binding = new SourceBinding($operation->binding(), $select->from);
        $column = $table->declarations()[0]->columns[1];

        self::assertSame(0, $binding->slot(0, $column));
        self::assertSame(0, $binding->slot(0, $column));
        self::assertSame('b', $binding->source()?->columns[0]->value);
    }

    public function testWriteResolvesDifferentSpellingsToTheSameDeclaredSlot(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('UPDATE t SET a = b', [$table]);
        self::assertInstanceOf(Update::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->target);

        self::assertSame(0, $binding->write(new Name('a')));
        self::assertSame(0, $binding->write(new Name('A')));
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }
}
