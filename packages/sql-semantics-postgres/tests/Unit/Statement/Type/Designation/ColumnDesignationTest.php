<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(ColumnDesignation::class)]
#[Small]
final class ColumnDesignationTest extends TestCase
{
    public function testTypeFactIsTheDeclaredTypeOfTheColumn(): void
    {
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $table = new Table(new QualifiedName(new Name('t')), $profile, [new Column(new Name('a'), Builtin::Uuid)]);
        $context = (new Platform())->context($profile, null, [$table], true);
        self::assertEquals(new Known(Builtin::Uuid), (new ColumnDesignation(new DottedName([new Name('t'), new Name('a')])))->typeFact($context, false));
    }

    public function testCatalogNameIsTheColumnName(): void
    {
        self::assertSame('a', (new ColumnDesignation(new DottedName([new Name('t'), new Name('a')])))->catalogName()->value);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new ColumnDesignation(new DottedName([new Name('t'), new Name('a')])))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesThePercentTypeSuffix(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ColumnDesignation(new DottedName([new Name('s'), new Name('t'), new Name('a')])))->render($out);
        self::assertSame('s.t.a % TYPE', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANameWithoutARelation(): void
    {
        $this->expectExceptionMessage('A column type reference names a relation and a column.');
        new ColumnDesignation(new DottedName([new Name('a')]));
    }
}
