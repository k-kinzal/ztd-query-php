<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Manipulation\AssignmentCasts::class)]
#[Medium]
final class AssignmentCastsTest extends TestCase
{
    public function testRefusedAcceptsNumericAndStringTargets(): void
    {
        $casts = new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\AssignmentCasts();
        self::assertSame([false, false, false, true, true], [$casts->refused(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Float8, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int2), $casts->refused(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Varchar), $casts->refused(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Timestamptz, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Date), $casts->refused(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), $casts->refused(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Date, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool)]);
    }

    public function testRefusedAcceptsTypesOutsideTheTables(): void
    {
        self::assertFalse((new \SqlSemantics\Platform\PostgreSql\Rules\Manipulation\AssignmentCasts())->refused(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Xml, \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4));
    }

    public function testCheckReportsAValueTheColumnCannotTake(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("UPDATE t SET a = 'x'::text", [$t, $u]);
        self::assertSame(['column "a" is of type integer but expression is of type text'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testCheckAcceptsAStringConstant(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("UPDATE t SET a = '1'", [$t, $u]);
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }
}
