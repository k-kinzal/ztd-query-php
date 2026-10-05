<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach::class)]
#[Medium]
final class LateralReachTest extends TestCase
{
    public function testBarMarksEveryRelationOnce(): void
    {
        $reach = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach();
        $barred = $reach->bar($reach->bar([new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Resolution\ImplicitSlot([new \SqlSemantics\Statement\Identifier\Name('ctid')], new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('ctid'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Statement\Type\Nullability::NotNull))])]));
        self::assertSame([-2], $barred[0]->hidden);
    }

    public function testBarredTellsABarredRelation(): void
    {
        $reach = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach();
        $relation = new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Resolution\ImplicitSlot([new \SqlSemantics\Statement\Identifier\Name('ctid')], new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('ctid'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Statement\Type\Nullability::NotNull))]);
        self::assertSame([false, true], [$reach->barred($relation), $reach->barred($reach->bar([$relation])[0])]);
    }

    public function testReachedFindsTheBarredRelationOfAQualifier(): void
    {
        $reach = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach();
        $relation = $reach->bar([new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Resolution\ImplicitSlot([new \SqlSemantics\Statement\Identifier\Name('ctid')], new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('ctid'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Statement\Type\Nullability::NotNull))])])[0];
        $environment = new \SqlSemantics\Resolution\Environment((new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true), null, [$relation]);
        $missing = new \SqlSemantics\Statement\Reference\Column\MissingColumn(new \SqlSemantics\Statement\Identifier\Name('zz'));
        self::assertSame($relation, $reach->reached($environment, $missing, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))));
    }

    public function testReachedFindsTheBarredRelationOfAResolvedColumn(): void
    {
        $reach = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach();
        $relation = $reach->bar([new \SqlSemantics\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), new \SqlSemantics\Statement\Shape\RowShape([new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Resolution\ImplicitSlot([new \SqlSemantics\Statement\Identifier\Name('ctid')], new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('ctid'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Statement\Type\Nullability::NotNull))])])[0];
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $environment = new \SqlSemantics\Resolution\Environment($context, new \SqlSemantics\Resolution\Environment($context, null, [$relation]));
        $resolved = new \SqlSemantics\Statement\Reference\Column\ResolvedColumn($relation->relation, $relation->shape->slots[0], 1);
        self::assertSame($relation, $reach->reached($environment, $resolved, null));
    }

    public function testReportNamesAnUnaliasedJoin(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT * FROM (SELECT 1 AS a) AS t JOIN (SELECT 2 AS b) AS u ON true FULL JOIN LATERAL (SELECT b AS x) AS s ON true', []);
        self::assertSame(['invalid reference to FROM-clause entry for table "unnamed_join"'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testReportNamesTheAliasOfAWholeRow(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT * FROM (SELECT 1 AS a) AS t RIGHT JOIN LATERAL (SELECT t AS x) AS s ON true', []);
        self::assertSame('invalid reference to FROM-clause entry for table "t"', $query->facts->diagnostics[0]->message());
    }

    public function testReportIsNotRaisedForALeftJoin(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT * FROM (SELECT 1 AS a) AS t LEFT JOIN LATERAL (SELECT t.a AS x) AS s ON true', []);
        self::assertSame([], $query->facts->diagnostics);
    }
}
