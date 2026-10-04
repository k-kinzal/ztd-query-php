<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\SchemaMember;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SchemaMember::class)]
#[Medium]
final class SchemaMemberTest extends TestCase
{
    public function testContextSearchesTheSchemaBeforeTheWrittenPath(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $create = $semantics->analyze('CREATE TABLE t (a int4)', []);
        $context = (new SchemaMember($create->statement, new Name('s')))->context($semantics->context([]));
        self::assertSame(['pg_temp', 'pg_catalog', 's', 'public'], array_map(static fn (Name $name): string => $name->value, $context->searchPath));
        self::assertSame('public', $context->declarationSchema->value);
    }

    public function testDeriveStatementDeclaresACreatedObjectInTheSchema(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $derivation = new Derivation($semantics->context([]));
        (new SchemaMember($semantics->analyze('CREATE TABLE t (a int4)', [])->statement, new Name('s')))->deriveStatement($derivation);
        self::assertSame('s', $derivation->facts()->declarations[0]->name->schema?->value);
    }

    public function testDeriveStatementDerivesAnotherStatementAsItIs(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $derivation = new Derivation($semantics->context([]));
        (new SchemaMember($semantics->analyze('SELECT 1', [])->statement, new Name('s')))->deriveStatement($derivation);
        self::assertSame([], $derivation->facts()->declarations);
    }

    public function testRenderWritesTheElement(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new SchemaMember((new Semantics(Dialect::PostgreSql))->analyze('CREATE TABLE t (a int4)', [])->statement, new Name('s')))->render($out);
        self::assertSame('CREATE TABLE t (a int4)', (new Lexical())->join($out->pieces()));
    }
}
