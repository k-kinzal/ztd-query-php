<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(SourceBinding::class)]
#[Medium]
final class SourceBindingTest extends TestCase
{
    public function testReferenceAndSourceKeepTheBoundColumnName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE public.t ("A" INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT "A" FROM public.t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $result);

        $binding = new SourceBinding($operation->binding(), $select->from);
        self::assertSame($select->from, $binding->relation());
        self::assertSame(0, $binding->reference($result->expression)->slot);
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }

    public function testSourceIsOptional(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->source());
    }

    public function testRelationReturnsTheBoundOccurrence(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->relation());
    }

    public function testTypeOfKeepsTheResolvedResultDomain(): void
    {
        $operation = (new Inspection())->inspect('SELECT CASE WHEN TRUE THEN 2 ELSE NULL END');
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);

        $type = (new SourceBinding($operation->binding(), null))->typeOf($target->expression);

        self::assertInstanceOf(Known::class, $type);
        self::assertSame(Builtin::Int4, $type->descriptor);
    }

    public function testWriteTargetUsesTheDeclaredColumnSpellingAndOrder(): void
    {
        $semantics = new Inspection();
        $declaration = $semantics->inspect('CREATE TABLE public.t (id INTEGER, payload INTEGER)');
        $operation = $semantics->inspect('SELECT id FROM public.t', [$declaration]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        $target = $binding->writeTarget(['payload', 'id']);

        self::assertSame('payload', $target->columns[0]->value);
        self::assertSame('id', $target->columns[1]->value);
    }

    public function testSlotForKeepsTheIdentityOfADeclaredColumn(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE public.t (id INTEGER)');
        $operation = $inspection->inspect('SELECT id FROM public.t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        $column = $binding->writableColumn('id');
        $before = $binding->slotFor($column);

        self::assertSame($before->slot, $binding->slotFor($column)->slot);
        self::assertSame(0, $before->occurrence);
    }

    public function testWritableColumnRejectsAnIneligibleType(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE public.t (a TEXT)');
        $operation = $inspection->inspect('SELECT a FROM public.t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        $this->expectExceptionMessage('writable signed 32-bit integer');

        $binding->writableColumn('a');
    }

    public function testResolvedReadsTheDeclarationSelectedByTheWildcard(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (id INTEGER, amount INTEGER)');
        $operation = $inspection->inspect('SELECT * FROM app.t', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $binding = $operation->binding();
        $fields = $binding->facts->query($statement)->fields();
        self::assertNotNull($fields);
        $resolution = $fields->at(1)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $source = new SourceBinding($binding, $statement->from);
        self::assertSame(0, $source->resolved($resolution)->slot);
        self::assertSame('amount', $source->source()?->columns[0]->value);
    }
}
