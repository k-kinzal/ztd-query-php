<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IncludedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexOrder;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CreateIndex::class)]
#[Small]
final class CreateIndexTest extends TestCase
{
    public function testToStringRetainsRepeatedKeyAndCoveringOccurrences(): void
    {
        $table = new IndexHeading(new IndexTarget(new Identifier('app'), new Identifier('orders')), [new Identifier('id'), new Identifier('other')], [ColumnKind::Int4, ColumnKind::Int4]);
        $key = new IndexKey(0, IndexOrder::DescendingNullsLast);
        $cover = new IncludedColumn(0);
        $index = new CreateIndex($table, new Identifier('odd"name'), [&$key, new IndexKey(0)], [&$cover, $cover], nullsNotDistinct: true);
        $key = new IndexKey(1);
        $cover = new IncludedColumn(1);

        self::assertSame(
            'CREATE INDEX "odd""name" ON "app"."orders" USING btree ("id" DESC NULLS LAST, "id" ASC NULLS LAST) INCLUDE ("id", "id") NULLS NOT DISTINCT',
            $index->toString(),
        );
    }

    public function testProfileIdReportsTheExactGrammarRelease(): void
    {
        $table = new IndexHeading(new IndexTarget(new Identifier('pg_temp'), new Identifier('orders')), [new Identifier('id')], [ColumnKind::Int4]);
        $index = new CreateIndex($table, new Identifier('ix'), [new IndexKey(0)], release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $index->profileId());
    }

    public function testToStringKeepsTheTextComparisonSeparateFromCoveringFields(): void
    {
        $heading = new IndexHeading(
            new IndexTarget(new Identifier('public'), new Identifier('items')),
            [new Identifier('count'), new Identifier('code'), new Identifier('payload')],
            [ColumnKind::Int4, ColumnKind::Text, ColumnKind::Text]
        );
        $direct = new CreateIndex(
            $heading,
            new Identifier('indexed'),
            [new IndexKey(1, IndexOrder::DescendingNullsLast, TextCollation::C)],
            [new IncludedColumn(2)]
        );
        $semantics = new Semantics();
        $bound = $semantics->context(['CREATE TABLE public.items (count integer, code text COLLATE pg_catalog."POSIX", payload text)']);
        $analysis = $semantics->analyze('CREATE INDEX indexed ON public.items (code COLLATE pg_catalog."C" DESC NULLS LAST) INCLUDE (payload)', $bound);

        self::assertEquals($direct, $analysis);
        self::assertSame([1, 2], array_keys($direct->bindings));
        self::assertSame(ColumnKind::Text, $direct->bindings[2]->kind);
        self::assertSame('CREATE INDEX "indexed" ON "public"."items" USING btree '
            . '("code" COLLATE pg_catalog."C" pg_catalog.text_ops DESC NULLS LAST) INCLUDE ("payload")', $direct->toString());
    }

    public function testToStringPreservesADeclaredCollationFromALikeCopy(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE public.source (code text COLLATE pg_catalog."C", payload text COLLATE pg_catalog."POSIX")',
            'CREATE TABLE public.copy (LIKE public.source)',
        ]);
        $inherited = $semantics->analyze('CREATE INDEX copied ON public.copy (code pg_catalog.text_ops)', $context);
        $explicit = $semantics->analyze('CREATE INDEX copied ON public.copy (code COLLATE pg_catalog."C")', $context);
        self::assertInstanceOf(CreateIndex::class, $inherited);
        self::assertEquals($explicit, $inherited);
        self::assertSame(TextCollation::C, $inherited->keys[0]->collation);
        self::assertSame('CREATE INDEX "copied" ON "public"."copy" USING btree '
            . '("code" COLLATE pg_catalog."C" pg_catalog.text_ops ASC NULLS LAST)', $inherited->toString());
    }
}
