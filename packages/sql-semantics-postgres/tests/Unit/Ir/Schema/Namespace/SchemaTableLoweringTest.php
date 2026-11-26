<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaTableLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaTableLowering::class)]
final class SchemaTableLoweringTest extends TestCase
{
    public function testLowerKeepsTableOrderInTheDeclaredNamespace(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze('CREATE SCHEMA archive CREATE TABLE newest (id integer) CREATE TABLE previous (wide bigint)');

        self::assertInstanceOf(CreateSchema::class, $value);
        self::assertSame(['archive', 'newest'], [$value->tables[0]->target->schema->value, $value->tables[0]->target->table->value]);
        self::assertSame(['archive', 'previous'], [$value->tables[1]->target->schema->value, $value->tables[1]->target->table->value]);
    }
}
