<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Index\SchemaIndexOrder;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaIndexOrder::class)]
final class SchemaIndexOrderTest extends TestCase
{
    public function testCheckRequiresTheExactOperandSlot(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE archive.ledger (zero integer, kept bigint)']);
        $index = $semantics->analyze('CREATE INDEX by_kept ON archive.ledger (kept)', $context);
        $table = $semantics->analyze('CREATE TABLE archive.ledger (kept bigint, zero integer)');
        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertInstanceOf(CreateTable::class, $table);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema index needs the exact ordered operand bindings.');

        new CreateSchema(new Identifier('archive'), tables: [$table], indexes: [$index]);
    }

    public function testCheckRequiresThePriorOccupantToAgreeWithTheGuardedIndex(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE archive.ledger (id integer)']);
        $index = $semantics->analyze('CREATE INDEX IF NOT EXISTS same ON archive.ledger (id)', $context);
        $root = $semantics->analyze('CREATE SCHEMA archive CREATE SEQUENCE same CREATE TABLE ledger (id integer)');
        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertInstanceOf(CreateSchema::class, $root);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema index must retain the actual presence of its name.');

        new CreateSchema($root->name, tables: $root->tables, sequences: $root->sequences, indexes: [$index]);
    }
}
