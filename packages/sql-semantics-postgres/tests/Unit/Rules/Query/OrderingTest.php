<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Ordering::class)]
#[Medium]
final class OrderingTest extends TestCase
{
    public function testSortPrefersTheOutputName(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT b AS a FROM t ORDER BY a', [$t, $u]);
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $term = $select->options?->order[0]->expression;
        self::assertNotNull($term);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\AliasTarget::class, $query->facts->scalar($term)->resolution);
    }

    public function testGroupPrefersTheInputColumn(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT b AS a FROM t GROUP BY a', [$t, $u]);
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $select);
        $item = $select->groupBy[0];
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $item);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $query->facts->scalar($item)->resolution);
    }

    public function testOutputsReportsAnExpressionAfterASetOperation(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 AS a UNION SELECT 2 ORDER BY a + 1');
        self::assertSame('invalid UNION/INTERSECT/EXCEPT ORDER BY clause', $query->facts->diagnostics[0]->message());
    }

    public function testTermReportsANonIntegerConstant(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT 1 ORDER BY 'x'");
        self::assertSame('non-integer constant in ORDER BY', $query->facts->diagnostics[0]->message());
    }

    public function testPositionsListsTheKnownFields(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $projection = [new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull))];
        self::assertCount(1, (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Ordering())->positions($projection, $derivation->environment())->aliases);
    }

    public function testKnownStopsAtAnOpenStar(): void
    {
        $projection = [new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull)), new \SqlSemantics\Statement\Shape\OpenStar([new \SqlSemantics\Statement\Reference\Missing\UndeclaredRelation(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')))]), new \SqlSemantics\Statement\Shape\Field(2, new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('b'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull))];
        self::assertCount(1, (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Ordering())->known($projection));
    }

    public function testNamedKeepsOneOfEqualExpressions(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT a, a FROM t ORDER BY a', [$t, $u]);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testSameComparesTheExpressions(): void
    {
        $first = new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')));
        $second = new \SqlSemantics\Statement\Shape\Field(1, new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')));
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Rules\Query\Ordering())->same($first, $second));
    }

    public function testBareFindsANameInParentheses(): void
    {
        self::assertSame('a', (new \SqlSemantics\Platform\PostgreSql\Rules\Query\Ordering())->bare(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference([new \SqlSemantics\Statement\Identifier\Name('a')])))?->value);
    }
}
