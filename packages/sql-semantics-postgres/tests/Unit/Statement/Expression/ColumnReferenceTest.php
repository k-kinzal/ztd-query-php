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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

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
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [new Column(new Name('a'), Builtin::Int4, Nullability::NotNull), new Column(new Name('b'), Builtin::Text)]);
        $context = (new Platform())->context($profile, null, [$table], true);
        $input = new TableInput(new RelationReference(new QualifiedName(new Name('t'))));
        $reference = new ColumnReference([new Name('public'), new Name('t'), new Name('a')]);
        $derivation = new Derivation($context);
        $derivation->query(new Select([new ExpressionTarget($reference)], $input), $derivation->environment());
        $fact = $derivation->facts()->scalar($reference);
        self::assertInstanceOf(ResolvedColumn::class, $fact->resolution);
        self::assertSame($table->columns[0], $fact->resolution->slot->column);
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
    }

    public function testDeriveScalarReportsAMissingColumn(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [new Column(new Name('a'), Builtin::Int4, Nullability::NotNull), new Column(new Name('b'), Builtin::Text)]);
        $context = (new Platform())->context($profile, null, [$table], true);
        $reference = new ColumnReference([new Name('c')]);
        $derivation = new Derivation($context);
        $derivation->query(new Select([new ExpressionTarget($reference)], new TableInput(new RelationReference(new QualifiedName(new Name('t'))))), $derivation->environment());
        self::assertInstanceOf(MissingColumn::class, $derivation->facts()->scalar($reference)->resolution);
        self::assertInstanceOf(Invalid::class, $derivation->facts()->scalar($reference)->type);
    }

    public function testDeriveScalarDependsOnAnUndeclaredRelation(): void
    {
        $reference = new ColumnReference([new Name('a')]);
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], false));
        $derivation->query(new Select([new ExpressionTarget($reference)], new TableInput(new RelationReference(new QualifiedName(new Name('t'))))), $derivation->environment());
        self::assertInstanceOf(ConditionalColumn::class, $derivation->facts()->scalar($reference)->resolution);
        self::assertInstanceOf(Dependent::class, $derivation->facts()->scalar($reference)->type);
    }

    public function testDeriveScalarReportsMoreThanFourParts(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new ColumnReference([new Name('a'), new Name('b'), new Name('c'), new Name('d'), new Name('e')]), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(ImproperName::class, $fact->type->cause);
    }

    public function testDeriveScalarHasNoRuleForAFieldOfACompositeColumn(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [new Column(new Name('a'), Builtin::Int4, Nullability::NotNull), new Column(new Name('b'), Builtin::Text)]);
        $context = (new Platform())->context($profile, null, [$table], true);
        $reference = new ColumnReference([new Name('a'), new Name('field')]);
        $derivation = new Derivation($context);
        $this->expectExceptionMessage('PG-COLUMN-REF-001: field selection from a composite column');
        $derivation->query(new Select([new ExpressionTarget($reference)], new TableInput(new RelationReference(new QualifiedName(new Name('t'))))), $derivation->environment());
    }

    public function testRenderWritesTheParts(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ColumnReference([new Name('T'), new Name('order')]))->render($out);
        self::assertSame('"T".order', (new Lexical())->join($out->pieces()));
    }
}
