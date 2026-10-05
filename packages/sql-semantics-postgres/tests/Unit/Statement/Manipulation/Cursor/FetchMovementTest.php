<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\FetchMovement::class)]
#[Medium]
final class FetchMovementTest extends TestCase
{
    public function testCountedTellsWhetherACountFollows(): void
    {
        self::assertSame([true, false], [\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\FetchMovement::BackwardCount->counted(), \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\FetchMovement::BackwardAll->counted()]);
    }

    public function testWriteWritesTheKeywords(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        self::assertSame(['FETCH c', 'FETCH FORWARD ALL c', 'MOVE 3 c', 'FETCH PRIOR c'], [$semantics->analyze('FETCH IN c')->toString(), $semantics->analyze('FETCH FORWARD ALL FROM c')->toString(), $semantics->analyze('MOVE 3 c')->toString(), $semantics->analyze('FETCH PRIOR c')->toString()]);
    }
}
