<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaViewLowering::class)]
final class SchemaViewLoweringTest extends TestCase
{
    public function testLowerSelectsTheEarlierEffectiveLocalTable(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.accounts (id bigint)']);
        $value = $semantics->analyze(
            'CREATE SCHEMA fresh CREATE VIEW report AS SELECT accounts.id FROM accounts'
            . ' CREATE TABLE accounts (id integer) CREATE TABLE IF NOT EXISTS accounts (other bigint)',
            $context,
        );
        self::assertInstanceOf(CreateSchema::class, $value);
        $view = $value->views[0];
        self::assertSame('fresh', $view->name->schema->value);
        self::assertInstanceOf(Select::class, $view->query);
        self::assertInstanceOf(TableSource::class, $view->query->source);
        self::assertSame('fresh', $view->query->source->schema->value);
        self::assertSame('id', $view->query->source->columns[0]->value);
        self::assertSame('int4', $view->query->source->columnKind(0)?->value);
    }
}
