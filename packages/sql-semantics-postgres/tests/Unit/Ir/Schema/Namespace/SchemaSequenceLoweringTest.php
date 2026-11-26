<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaSequenceLowering::class)]
final class SchemaSequenceLoweringTest extends TestCase
{
    public function testLowerBindsTheNewNamespaceAndKeepsSequenceOrder(): void
    {
        $semantics = new Semantics();
        $schema = $semantics->analyze(
            'CREATE SCHEMA "Batch.棚" CREATE SEQUENCE first_seq AS smallint START 3 RESTART 8'
            . ' CREATE TABLE IF NOT EXISTS first_seq (n integer) CREATE UNLOGGED SEQUENCE second_seq',
        );

        self::assertInstanceOf(CreateSchema::class, $schema);
        self::assertSame('Batch.棚', $schema->sequences[0]->name->schema->value);
        self::assertSame('8', $schema->sequences[0]->parameters->initial->decimal);
        self::assertSame('second_seq', $schema->sequences[1]->name->name->value);
        self::assertSame('first_seq', $schema->tables[0]->target->table->value);
        self::assertTrue(strpos($schema->toString(), 'CREATE UNLOGGED SEQUENCE') < strpos($schema->toString(), 'CREATE TABLE'));
    }
}
