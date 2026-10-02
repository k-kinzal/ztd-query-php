<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TableInput::class)]
#[Small]
final class TableInputTest extends TestCase
{
    public function testNameIsTheRelationName(): void
    {
        self::assertSame('t', (new TableInput(new RelationReference(new QualifiedName(new Name('t'), new Name('s')))))->name()->name->value);
    }

    public function testAliasIsTheCorrelationName(): void
    {
        self::assertSame('x', (new TableInput(new RelationReference(new QualifiedName(new Name('t'))), new Name('x')))->alias()?->value);
    }

    public function testDeriveRelationHasOneSlotPerDeclaredColumn(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [new Column(new Name('a'), Builtin::Int4, Nullability::NotNull), new Column(new Name('b'), Builtin::Text)]);
        $context = (new Platform())->context($profile, null, [$table], true);
        $derivation = new Derivation($context);
        $fact = $derivation->relation(new TableInput(new RelationReference(new QualifiedName(new Name('t')))), $derivation->environment());
        self::assertCount(2, $fact->shape->slots);
        self::assertSame($table->columns[1], $fact->shape->slots[1]->column);
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
    }

    public function testDeriveRelationIsOpenForAnUndeclaredRelation(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false));
        $fact = $derivation->relation(new TableInput(new RelationReference(new QualifiedName(new Name('t')))), $derivation->environment());
        self::assertFalse($fact->shape->complete());
        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
    }

    public function testDeriveRelationReportsAMissingRelation(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->relation(new TableInput(new RelationReference(new QualifiedName(new Name('t')))), $derivation->environment());
        self::assertInstanceOf(MissingTable::class, $fact->table);
        self::assertCount(1, $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheAliasAfterAs(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new TableInput(new RelationReference(new QualifiedName(new Name('t')), true), new Name('left')))->render($out);
        self::assertSame('ONLY t AS "left"', (new Lexical())->join($out->pieces()));
    }
}
