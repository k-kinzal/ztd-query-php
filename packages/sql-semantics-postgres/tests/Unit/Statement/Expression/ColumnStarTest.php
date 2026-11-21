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
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

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
