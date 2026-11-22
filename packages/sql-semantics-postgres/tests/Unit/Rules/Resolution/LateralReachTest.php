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
        $barred = $reach->bar($reach->bar([new \SqlSemantics\Platform\PostgreSql\Model\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')))), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Platform\PostgreSql\Model\Resolution\ImplicitSlot([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid')], new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull))])]));
        self::assertSame([-2], $barred[0]->hidden);
    }

    public function testBarredTellsABarredRelation(): void
    {
        $reach = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach();
        $relation = new \SqlSemantics\Platform\PostgreSql\Model\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')))), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Platform\PostgreSql\Model\Resolution\ImplicitSlot([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid')], new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull))]);
        self::assertSame([false, true], [$reach->barred($relation), $reach->barred($reach->bar([$relation])[0])]);
    }

    public function testReachedFindsTheBarredRelationOfAQualifier(): void
    {
        $reach = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach();
        $relation = $reach->bar([new \SqlSemantics\Platform\PostgreSql\Model\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')))), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Platform\PostgreSql\Model\Resolution\ImplicitSlot([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid')], new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull))])])[0];
        $environment = new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment((new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172), null, [], true), null, [$relation]);
        $missing = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\MissingColumn(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('zz'));
        self::assertSame($relation, $reach->reached($environment, $missing, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'))));
    }

    public function testReachedFindsTheBarredRelationOfAResolvedColumn(): void
    {
        $reach = new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach();
        $relation = $reach->bar([new \SqlSemantics\Platform\PostgreSql\Model\Resolution\VisibleRelation(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')))), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]), null, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), [], [new \SqlSemantics\Platform\PostgreSql\Model\Resolution\ImplicitSlot([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid')], new \SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ctid'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Tid), \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull))])])[0];
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $environment = new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($context, new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($context, null, [$relation]));
        $resolved = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn($relation->relation, $relation->shape->slots[0], 1);
        self::assertSame($relation, $reach->reached($environment, $resolved, null));
    }

    public function testReportNamesAnUnaliasedJoin(): void
    {
        $query = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('SELECT * FROM (SELECT 1 AS a) AS t JOIN (SELECT 2 AS b) AS u ON true FULL JOIN LATERAL (SELECT b AS x) AS s ON true', []);
        self::assertSame(['invalid reference to FROM-clause entry for table "unnamed_join"'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testReportNamesTheAliasOfAWholeRow(): void
    {
        $query = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('SELECT * FROM (SELECT 1 AS a) AS t RIGHT JOIN LATERAL (SELECT t AS x) AS s ON true', []);
        self::assertSame('invalid reference to FROM-clause entry for table "t"', $query->facts->diagnostics[0]->message());
    }

    public function testReportIsNotRaisedForALeftJoin(): void
    {
        $query = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('SELECT * FROM (SELECT 1 AS a) AS t LEFT JOIN LATERAL (SELECT t.a AS x) AS s ON true', []);
        self::assertSame([], $query->facts->diagnostics);
    }
}
