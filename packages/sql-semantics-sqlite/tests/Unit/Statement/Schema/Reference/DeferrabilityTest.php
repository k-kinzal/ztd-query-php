<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\Deferrability;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\InitialMode;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Deferrability::class)]
#[Medium]
final class DeferrabilityTest extends TestCase
{
    public function testDeferredHoldsOnlyForDeferrableInitiallyDeferred(): void
    {
        self::assertTrue((new Deferrability(true, InitialMode::Deferred))->deferred());
        self::assertFalse((new Deferrability(true, InitialMode::Immediate))->deferred());
        self::assertFalse((new Deferrability(true))->deferred());
        self::assertFalse((new Deferrability(false, InitialMode::Deferred))->deferred());
    }

    public function testDeriveConstraintRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent DEFERRABLE)', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesEveryForm(): void
    {
        $lexical = new Lexical();
        $plain = new Output(new Codec());
        (new Deferrability(true))->render($plain);
        $negated = new Output(new Codec());
        (new Deferrability(false, InitialMode::Immediate))->render($negated);

        self::assertSame('DEFERRABLE', $lexical->join($plain->pieces()));
        self::assertSame('NOT DEFERRABLE INITIALLY IMMEDIATE', $lexical->join($negated->pieces()));
    }

    public function testRenderKeepsTheClauseAsAColumnConstraintOfItsOwn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table c (p references parent not deferrable initially deferred)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(Deferrability::class, $statement->columns[0]->constraints[1]);
        self::assertSame('CREATE TABLE c (p REFERENCES parent NOT DEFERRABLE INITIALLY DEFERRED)', $operation->toString());
    }
}
