<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\DerivedBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinKind;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
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

    public function testTypeOfReadsTheCaseResultAgainstBothInputs(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE public.t (id INTEGER)');
        $operation = $inspection->inspect('SELECT CASE WHEN a.id > 0 THEN b.id ELSE a.id END FROM public.t a CROSS JOIN public.t b', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        $binding = JoinBinding::bind($operation->binding(), $operation->statement->from, true);
        self::assertInstanceOf(JoinBinding::class, $binding);

        $type = $binding->typeOf($target->expression);

        self::assertInstanceOf(Known::class, $type);
        self::assertSame(Builtin::Int4, $type->descriptor);
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

    public function testBindChildRecognizesAResolvedDerivedInput(): void
    {
        $operation = (new Inspection())->inspect('SELECT q.x FROM (SELECT 3 AS x) q');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(DerivedTable::class, $operation->statement->from);
        self::assertInstanceOf(DerivedBinding::class, JoinBinding::bindChild($operation->binding(), $operation->statement->from, true));
    }

    public function testUngroupPreservesAnAbsentSource(): void
    {
        self::assertNull(JoinBinding::ungroup(null));
    }

    public function testResolvedFindsTheQualifiedWildcardOccurrence(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (id INTEGER, amount INTEGER)');
        $operation = $inspection->inspect('SELECT b.* FROM app.t AS a CROSS JOIN app.t AS b', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $binding = $operation->binding();
        $fields = $binding->facts->query($statement)->fields();
        self::assertNotNull($fields);
        $resolution = $fields->at(1)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $source = JoinBinding::bind($binding, $statement->from, true);
        self::assertInstanceOf(JoinBinding::class, $source);
        $reference = $source->resolved($resolution);

        self::assertSame(1, $reference->occurrence);
        self::assertSame(0, $reference->slot);
    }
}
