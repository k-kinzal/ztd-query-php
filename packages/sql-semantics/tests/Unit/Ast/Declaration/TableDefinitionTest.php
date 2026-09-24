<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\TableDefinition;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Dialect;

#[CoversClass(TableDefinition::class)]
#[Medium]
final class TableDefinitionTest extends TestCase
{
    public function testReaderResolvesNamespaceColumnsConstraintsAndOptions(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TEMP TABLE app.t(id INTEGER PRIMARY KEY, n TEXT, CHECK (id > 0)) WITH (fillfactor = 70)');
        $table = (new SchemaReader(new Identifiers(Dialect::PostgreSql), 'public'))->table($tree);
        self::assertSame('app', $table->schema);
        self::assertSame('t', $table->name);
        self::assertTrue($table->resolved);
        self::assertSame(['id', 'n'], array_column($table->columns, 'name'));
        self::assertCount(2, $table->constraints);
        self::assertSame([], $table->indexes);
        self::assertSame(['temporary' => true, 'fillfactor' => '70'], $table->options);
        self::assertSame($tree, $table->source);
    }

    public function testReaderCollectsInlineMysqlIndexesUnderTheDefaultNamespace(): void
    {
        $tree = (new DialectParser(Dialect::MySql))->parse('CREATE TABLE t(id INT, n VARCHAR(10), KEY ix (n)) ENGINE=InnoDB');
        $table = (new SchemaReader(new Identifiers(Dialect::MySql), ''))->table($tree);
        self::assertSame('', $table->schema);
        self::assertSame('t', $table->name);
        self::assertSame(['engine' => 'InnoDB'], $table->options);
        self::assertSame(['ix'], array_column($table->indexes, 'name'));
        self::assertSame(['', 't'], $table->indexes[0]->table);
    }

    public function testDefaultsMarkTheDeclarationResolvedWithoutIndexes(): void
    {
        $source = new Node('CreateStmt', 0, []);
        $table = new TableDefinition('main', 't', [], [], $source);
        self::assertTrue($table->resolved);
        self::assertSame([], $table->indexes);
        self::assertSame([], $table->options);
        self::assertFalse((new TableDefinition('main', 't', [], [], $source, resolved: false))->resolved);
    }
}
