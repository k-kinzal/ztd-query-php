<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Catalog;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;

#[CoversClass(Catalog::class)]
#[Small]
final class CatalogTest extends TestCase
{
    public function testBuildBindsSqlDefinitions(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $catalog = new Catalog($profile, null, $platform->parser($profile));
        $context = $catalog->build(['CREATE TABLE public.accounts (id INTEGER)'], true);

        self::assertTrue($context->complete);
        self::assertCount(1, $context->tables);
        self::assertSame('accounts', $context->tables[0]->name->name->value);
    }

    public function testBuildResolvesTypesAtTheDeclarationPosition(): void
    {
        $semantics = new Semantics();
        $declarations = [
            'CREATE TABLE public.before_shadow (label text)',
            'CREATE TEMP TABLE text (part INTEGER)',
            'CREATE TABLE public.after_shadow (label text)',
            'CREATE TABLE public.qualified (label pg_catalog.text)',
        ];
        $context = $semantics->context($declarations);
        $byName = [];
        foreach ($context->tables as $table) {
            $byName[$table->name->name->value] = $table;
        }

        self::assertSame(Builtin::Text, $byName['before_shadow']->columns[0]->type);
        self::assertInstanceOf(NamedOnPath::class, $byName['after_shadow']->columns[0]->type);
        self::assertSame(Builtin::Text, $byName['qualified']->columns[0]->type);
    }

    public function testBuildDoesNotResolveAnUnqualifiedTypeInAnOpenContext(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $catalog = new Catalog($profile, null, $platform->parser($profile));
        $context = $catalog->build(['CREATE TABLE public.accounts (label text)'], false);

        self::assertInstanceOf(NamedOnPath::class, $context->tables[0]->columns[0]->type);
    }

    public function testBuildRejectsSqlThatDeclaresNoRelation(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $catalog = new Catalog($profile, null, $platform->parser($profile));
        $this->expectExceptionMessage('A catalog definition must declare a relation.');

        $catalog->build(['SELECT 1'], true);
    }

    public function testResolveChecksThePreparedContext(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $catalog = new Catalog($profile, null, $platform->parser($profile));
        $context = $catalog->build(null, false);

        self::assertFalse($context->complete);
        self::assertSame($context, $catalog->resolve($context));
    }
}
