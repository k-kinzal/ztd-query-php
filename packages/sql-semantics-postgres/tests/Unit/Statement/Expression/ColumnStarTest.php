<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnStar::class)]
#[Small]
final class ColumnStarTest extends TestCase
{
    public function testOutputNameIsTheRelation(): void
    {
        self::assertSame('t', (new ColumnStar([new Name('s'), new Name('t')]))->outputName()->value);
    }

    public function testDeriveScalarIsTheWholeRowOfTheRelation(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $slot = new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull);
        $environment = new Environment($context, null, [new VisibleRelation(self::createStub(Relation::class), new RowShape([$slot]), null, new QualifiedName(new Name('t'), new Name('s')))]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new ColumnStar([new Name('s'), new Name('t')]), $environment);
        self::assertEquals(new Known(new Composite([$slot], new Name('t'))), $fact->type);
    }

    public function testDeriveScalarReportsARelationThatIsNotVisible(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new ColumnStar([new Name('t')]), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(MissingTable::class, $fact->type->cause);
    }

    public function testRenderWritesTheStar(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ColumnStar([new Name('s'), new Name('t')]))->render($out);
        self::assertSame('s.t.*', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsNoQualifier(): void
    {
        $this->expectExceptionMessage('A star reference names its relation.');
        new ColumnStar([]);
    }
}
