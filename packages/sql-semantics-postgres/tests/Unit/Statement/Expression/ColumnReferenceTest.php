<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\VisibleRelation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(ColumnReference::class)]
#[Small]
final class ColumnReferenceTest extends TestCase
{
    public function testOutputNameIsTheLastPart(): void
    {
        self::assertSame('a', (new ColumnReference([new Name('s'), new Name('t'), new Name('a')]))->outputName()->value);
    }

    public function testDeriveScalarResolvesAQualifiedColumn(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $slot = new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull);
        $environment = new Environment($context, null, [new VisibleRelation(self::createStub(Relation::class), new RowShape([$slot]), null, new QualifiedName(new Name('t'), new Name('s')))]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new ColumnReference([new Name('s'), new Name('t'), new Name('a')]), $environment);
        self::assertInstanceOf(ResolvedColumn::class, $fact->resolution);
        self::assertSame($slot, $fact->resolution->slot);
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
    }

    public function testDeriveScalarReadsAOneWordNameAsTheWholeRowOfARelation(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $slot = new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull);
        $environment = new Environment($context, null, [new VisibleRelation(self::createStub(Relation::class), new RowShape([$slot]), new Name('t'))]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new ColumnReference([new Name('t')]), $environment);
        self::assertEquals(new Known(new Composite([$slot], new Name('t'))), $fact->type);
    }

    public function testDeriveScalarReportsAMissingColumn(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $environment = new Environment($context, null, [new VisibleRelation(self::createStub(Relation::class), new RowShape([]), new Name('t'))]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new ColumnReference([new Name('t'), new Name('c')]), $environment);
        self::assertInstanceOf(MissingColumn::class, $fact->resolution);
        self::assertInstanceOf(Invalid::class, $fact->type);
    }

    public function testDeriveScalarReportsMoreThanFourParts(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new ColumnReference([new Name('a'), new Name('b'), new Name('c'), new Name('d'), new Name('e')]), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(ImproperName::class, $fact->type->cause);
    }

    public function testRenderWritesTheParts(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ColumnReference([new Name('T'), new Name('order')]))->render($out);
        self::assertSame('"T".order', (new Lexical())->join($out->pieces()));
    }
}
