<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\Conditional\SqliteCaseArm;
use SqlSemantics\Statement\Expression\Conditional\SqliteCaseBranches;
use SqlSemantics\Statement\Expression\Conditional\SqliteSimpleCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;

#[CoversClass(SqliteSimpleCase::class)]
#[Small]
final class SqliteSimpleCaseTest extends TestCase
{
    public function testTypeUsesTheFallbackForAnAlwaysNullBase(): void
    {
        $null = new NullConstant();
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $case = new SqliteSimpleCase($null, new SqliteCaseBranches($one, new SqliteCaseArm($null, $null)));
        self::assertEquals($one->type(), $case->type());
        self::assertSame(Nullability::NotNull, $case->nullability());
    }

    public function testTypeStillDiagnosesUnvisitedBranchReferences(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $column = new ColumnReference($scope, new Name('missing'));
        $null = new NullConstant();
        $case = new SqliteSimpleCase($null, new SqliteCaseBranches($null, new SqliteCaseArm($null, $column)));
        self::assertSame(Invalid::MissingColumn, $case->type());
        self::assertSame(Nullability::Unknown, $case->nullability());
    }

    public function testNullabilityRetainsPossibleBranchNullsForOtherBases(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $case = new SqliteSimpleCase($one, new SqliteCaseBranches(null, new SqliteCaseArm($one, $one)));
        self::assertSame(Nullability::MaybeNull, $case->nullability());
        self::assertInstanceOf(SqliteChoiceDomain::class, $case->type());
    }

    public function testReferencesKeepsTheSingleBaseOccurrence(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $column = new ColumnReference($scope, new Name('input'));
        $null = new NullConstant();
        $case = new SqliteSimpleCase($column, new SqliteCaseBranches(null, new SqliteCaseArm($null, $null), new SqliteCaseArm($null, $null)));
        self::assertSame([$column], $case->references());
    }

    public function testWithBasePreservesTheOriginalAndSharedBranches(): void
    {
        $null = new NullConstant();
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $branches = new SqliteCaseBranches(null, new SqliteCaseArm($null, $null));
        $original = new SqliteSimpleCase($null, $branches);
        $updated = $original->withBase($one);
        self::assertSame($null, $original->base);
        self::assertSame($one, $updated->base);
        self::assertSame($branches, $updated->branches);
    }

    public function testWithBranchesPreservesSingleBaseIdentity(): void
    {
        $null = new NullConstant();
        $branches = new SqliteCaseBranches(null, new SqliteCaseArm($null, $null));
        $original = new SqliteSimpleCase($null, $branches);
        $replacement = $branches->withOtherwise($null);
        $updated = $original->withBranches($replacement);
        self::assertSame($null, $updated->base);
        self::assertSame($branches, $original->branches);
        self::assertSame($replacement, $updated->branches);
    }

    public function testToStringRetainsEqualityChoiceRatherThanTruthTests(): void
    {
        $text = new SqliteText(new StringLiteral('word'));
        $case = new SqliteSimpleCase($text, new SqliteCaseBranches(null, new SqliteCaseArm($text, $text)));
        self::assertSame("CASE 'word' WHEN 'word' THEN 'word' END", $case->toString());
    }
}
