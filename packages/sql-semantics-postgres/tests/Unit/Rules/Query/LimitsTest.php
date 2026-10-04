<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Limits::class)]
#[Small]
final class LimitsTest extends TestCase
{
    public function testDeriveSeesTheEnclosingQueryOnly(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT (SELECT 1 LIMIT t.a) FROM t', [$t, $u]);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testTiesReportsSkipLocked(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 ORDER BY 1 FETCH FIRST ROW WITH TIES FOR UPDATE SKIP LOCKED');
        self::assertSame('SKIP LOCKED and WITH TIES options cannot be used together', $query->facts->diagnostics[0]->message());
    }

    public function testLockedReportsAQualifiedRelation(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 FROM t FOR UPDATE OF s.t', [$t, $u]);
        self::assertSame('SELECT FOR UPDATE/SHARE must specify unqualified relation names', $query->facts->diagnostics[0]->message());
    }
}
