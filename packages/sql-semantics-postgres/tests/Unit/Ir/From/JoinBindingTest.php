<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinKind;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(JoinBinding::class)]
#[Medium]
final class JoinBindingTest extends TestCase
{
    public function testReferenceKeepsSelfJoinOccurrencesSeparate(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE public.t (id INTEGER)');
        $operation = $semantics->inspect('SELECT b.id FROM public.t AS a LEFT JOIN public.t AS b ON a.id = b.id', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $column = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $column);

        $binding = JoinBinding::bind($operation->binding(), $select->from, true);
        self::assertInstanceOf(JoinBinding::class, $binding);
        self::assertSame(1, $binding->reference($column->expression)->occurrence);
        self::assertSame(0, $binding->reference($column->expression)->slot);
    }

    public function testBindKeepsAnAbsentSource(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');
        $binding = JoinBinding::bind($operation->binding(), null, true);

        self::assertInstanceOf(SourceBinding::class, $binding);
        self::assertNull($binding->source());
    }

    public function testSourceClosesBothOperandsAndTheOperator(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE public.t (id INTEGER)');
        $operation = $semantics->inspect('SELECT 1 FROM public.t AS a LEFT JOIN public.t AS b ON a.id = b.id', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = JoinBinding::bind($operation->binding(), $operation->statement->from, true);
        self::assertInstanceOf(JoinBinding::class, $binding);

        $source = $binding->source();
        self::assertSame(JoinKind::Left, $source->kind);
        self::assertCount(2, $source->inputs());
    }
}
