<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\IndexDefinition;
use SqlSemantics\Ast\Definition\IndexReader;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Dialect;

#[CoversClass(IndexDefinition::class)]
#[Medium]
final class IndexDefinitionTest extends TestCase
{
    public function testReaderRetainsKeysIncludesPredicateAndOptions(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE UNIQUE INDEX ix ON app.t USING btree (id DESC NULLS LAST, (id + 1)) INCLUDE (n) WITH (fillfactor = 70) WHERE id > 0');
        $index = (new IndexReader(new Identifiers(Dialect::PostgreSql), 'public'))->read($tree);
        self::assertNotNull($index);
        self::assertSame('app', $index->schema);
        self::assertSame('ix', $index->name);
        self::assertSame(['app', 't'], $index->table);
        self::assertCount(2, $index->elements);
        self::assertTrue($index->unique);
        self::assertSame('btree', $index->method);
        self::assertSame(['n'], $index->include);
        self::assertSame('id > 0', trim($index->predicate?->toString() ?? ''));
        self::assertSame(['fillfactor' => '70'], $index->options);
        self::assertSame('CREATE UNIQUE INDEX ix ON app.t USING btree (id DESC NULLS LAST, (id + 1)) INCLUDE (n) WITH (fillfactor = 70) WHERE id > 0', trim($index->source->toString()));
    }

    public function testDefaultsLeaveTheOptionsEmpty(): void
    {
        $source = new Node('IndexStmt', 0, []);
        $index = new IndexDefinition('main', null, ['main', 't'], [], false, null, [], null, $source);
        self::assertNull($index->name);
        self::assertSame([], $index->elements);
        self::assertFalse($index->unique);
        self::assertNull($index->method);
        self::assertSame([], $index->include);
        self::assertNull($index->predicate);
        self::assertSame([], $index->options);
    }
}
