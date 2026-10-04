<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility::class)]
#[Small]
final class VisibilityTest extends TestCase
{
    public function testQualifiedOnlyHidesEverySlot(): void
    {
        $relation = new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('b'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('s')));
        self::assertSame([-1, 0, 1], (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->qualifiedOnly($relation)->hidden);
    }

    public function testRestrictedTellsAQualifiedOnlyRelation(): void
    {
        $relation = new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('b'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('s')));
        self::assertSame([false, true], [(new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->restricted($relation), (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->restricted((new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->qualifiedOnly($relation))]);
    }

    public function testAdmitsMatchesTheNameAndSchema(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $relation = new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('b'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('s')));
        $visibility = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility();
        self::assertSame([true, true, false], [$visibility->admits($derivation->environment(), $relation, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), $visibility->admits($derivation->environment(), $relation, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('s'))), $visibility->admits($derivation->environment(), $relation, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('x')))]);
    }

    public function testStarSkipsQualifiedOnlyRelations(): void
    {
        $relation = new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('b'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('s')));
        self::assertSame([2, 0], [count((new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->star([$relation])->slots), count((new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->star([(new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->qualifiedOnly($relation)])->slots)]);
    }

    public function testUnhiddenSkipsHiddenSlots(): void
    {
        $relation = new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('b'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, null, [0]);
        self::assertCount(1, (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility())->unhidden($relation));
    }
}
