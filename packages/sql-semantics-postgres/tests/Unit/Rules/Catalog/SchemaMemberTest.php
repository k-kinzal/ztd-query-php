<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\SchemaMember;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(SchemaMember::class)]
#[Medium]
final class SchemaMemberTest extends TestCase
{
    public function testContextSearchesTheSchemaBeforeTheWrittenPath(): void
    {
        $semantics = new Inspection();
        $create = $semantics->inspect('CREATE TABLE t (a int4)', []);
        $context = (new SchemaMember($create->statement, new Name('s')))->context($semantics->context([]));
        self::assertSame(['pg_temp', 'pg_catalog', 's', 'public'], array_map(static fn (Name $name): string => $name->value, $context->searchPath));
        self::assertSame('s', $context->declarationSchema->value);
    }

    public function testContextAddsTheRelationsTheStatementProvided(): void
    {
        $semantics = new Inspection();
        $create = $semantics->inspect('CREATE TABLE t (a int4)', []);
        $context = (new SchemaMember($create->statement, new Name('s')))->context($semantics->context([]), $create->declarations());
        self::assertSame($create->declarations(), $context->tables);
    }

    public function testStepRunsSequencesBeforeTablesAndGrantsLast(): void
    {
        $semantics = new Inspection();
        $sequence = new SchemaMember($semantics->inspect('CREATE SEQUENCE q', [])->statement, new Name('s'));
        $table = new SchemaMember($semantics->inspect('CREATE TABLE t (a int4)', [])->statement, new Name('s'));
        $grant = new SchemaMember($semantics->inspect('GRANT SELECT ON t TO joe', [])->statement, new Name('s'));
        self::assertSame([0, 1, 5], [$sequence->step(), $table->step(), $grant->step()]);
    }

    public function testDeriveStatementDeclaresACreatedObjectInTheSchema(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context([]));
        (new SchemaMember($semantics->inspect('CREATE TABLE t (a int4)', [])->statement, new Name('s')))->deriveStatement($derivation);
        self::assertSame('s', $derivation->facts()->declarations[0]->name->schema?->value);
    }

    public function testDeriveStatementDerivesAnotherStatementAsItIs(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context([]));
        (new SchemaMember($semantics->inspect('SELECT 1', [])->statement, new Name('s')))->deriveStatement($derivation);
        self::assertSame([], $derivation->facts()->declarations);
    }

    public function testRenderWritesTheElement(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new SchemaMember((new Inspection())->inspect('CREATE TABLE t (a int4)', [])->statement, new Name('s')))->render($out);
        self::assertSame('CREATE TABLE t (a int4)', (new Lexical())->join($out->pieces()));
    }
}
