<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection as P;
use SqlSemantics\Statement\Reference as R;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\Validation\Correspondence as V;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(V\ResolutionMatch::class)]
#[Small]
final class ResolutionMatchTest extends TestCase
{
    public function testCheckRejectsAConditionalLookupWithTheWrongOccurrence(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $name = new QualifiedName(new Name('items'));
        $expected = new R\CandidateColumn(new TableReference($catalog, $name));
        $actual = new R\CandidateColumn(new TableReference($catalog, $name));
        $this->expectException(InvariantViolation::class);
        (new V\ResolutionMatch())->check($expected, $actual);
    }

    public function testCheckRejectsADifferentAliasSlotEvenWhenBothHaveTheSameNameAndValue(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $name = new Name('answer');
        $value = new E\NullConstant();
        $first = new P\Field($value, $name);
        $second = new P\Field($value, $name);
        $fields = new P\Fields($scope, $first, $second);
        $this->expectException(InvariantViolation::class);
        (new V\ResolutionMatch())->check(new R\NamedAlias($fields, $first, $name), new R\NamedAlias($fields, $second, $name));
    }

    public function testCheckRejectsAnOuterFallbackWithADifferentParent(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $name = new Name('missing');
        $this->expectException(InvariantViolation::class);
        (new V\ResolutionMatch())->check(new R\OuterLookup(new Scope($catalog), $name), new R\OuterLookup(new Scope($catalog), $name));
    }

    public function testCheckRejectsASelfJoinOccurrenceWithTheSameDeclaration(): void
    {
        $column = new \SqlSemantics\Statement\Schema\Column(new Name('id'), new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::Integer));
        $table = new Table(new QualifiedName(new Name('items')), columns: $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), tables: $table);
        $left = new TableReference($catalog, $table->name);
        $right = new TableReference($catalog, $table->name);
        $this->expectException(InvariantViolation::class);
        (new V\ResolutionMatch())->check(new R\ResolvedColumn($left, $table, $column), new R\ResolvedColumn($right, $table, $column));
    }

    public function testSequenceRejectsTheLossOfAnAlternative(): void
    {
        $this->expectException(InvariantViolation::class);
        (new V\ResolutionMatch())->sequence([R\MissingColumn::Value], []);
    }

    public function testSequenceAcceptsTheExactOrderedOutcomes(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        (new V\ResolutionMatch())->sequence([new R\OuterLookup($scope, new Name('missing')), R\MissingColumn::Value], [new R\OuterLookup($scope, new Name('missing')), R\MissingColumn::Value]);
        self::assertSame(R\MissingColumn::Value, $scope->resolve(new Name('missing')));
    }
}
