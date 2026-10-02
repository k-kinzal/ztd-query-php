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
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(SqliteCaseBranches::class)]
#[Small]
final class SqliteCaseBranchesTest extends TestCase
{
    public function testTypeKeepsMixedRuntimeResultDomainsInsteadOfUnknown(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $text = new SqliteText(new StringLiteral('matched'));
        $branches = new SqliteCaseBranches($text, new SqliteCaseArm($one, $one));
        $type = $branches->type();
        self::assertInstanceOf(SqliteChoiceDomain::class, $type);
        self::assertEquals([$one->type(), $text->type()], $type->alternatives);
    }

    public function testInvalidDiagnosesEvenAnUnvisitedBranch(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $missing = new ColumnReference($scope, new Name('missing'));
        $branches = new SqliteCaseBranches(null, new SqliteCaseArm(new NullConstant(), $missing));
        self::assertSame(Invalid::MissingColumn, $branches->invalid());
        self::assertSame(Invalid::MissingColumn, $branches->type());
        self::assertSame(Nullability::Unknown, $branches->nullability());
    }

    public function testNullabilityUsesResultFactsRatherThanTestNullability(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $branches = new SqliteCaseBranches($one, new SqliteCaseArm(new NullConstant(), $one));
        self::assertSame(Nullability::NotNull, $branches->nullability());
        self::assertSame(Nullability::MaybeNull, (new SqliteCaseBranches(null, new SqliteCaseArm(new NullConstant(), $one)))->nullability());
    }

    public function testTypeRecognizesAnEntirelyNullResultDomain(): void
    {
        $null = new NullConstant();
        $branches = new SqliteCaseBranches(null, new SqliteCaseArm($null, $null));
        self::assertSame(NullDomain::Null, $branches->type());
        self::assertSame(Nullability::AlwaysNull, $branches->nullability());
    }

    public function testResultsIncludesTheImplicitNullFallback(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $results = (new SqliteCaseBranches(null, new SqliteCaseArm($one, $one)))->results();
        self::assertSame($one, $results[0]);
        self::assertInstanceOf(NullConstant::class, $results[1]);
    }

    public function testOperandsPreservesTestThenResultOrder(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $two = new SqliteInteger(new UnsignedInteger('2'));
        $fallback = new NullConstant();
        $branches = new SqliteCaseBranches($fallback, new SqliteCaseArm($one, $two), new SqliteCaseArm($two, $one));
        self::assertSame([$one, $two, $two, $one, $fallback], $branches->operands());
    }

    public function testReferencesRetainsLookupIdentityAcrossAllBranches(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('bar'))));
        $column = new ColumnReference($scope, new Name('input'));
        $branches = new SqliteCaseBranches($column, new SqliteCaseArm($column, $column));
        self::assertSame([$column, $column, $column], $branches->references());
        self::assertNull($branches->invalid());
        self::assertSame(Nullability::Unknown, $branches->nullability());
        $type = $branches->type();
        self::assertInstanceOf(SqliteChoiceDomain::class, $type);
        self::assertSame([Unresolved::MissingDeclaration], $type->alternatives);
    }

    public function testToStringPreservesOrderedChoicesAndExplicitFallback(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $null = new NullConstant();
        self::assertSame('WHEN 1 THEN NULL WHEN NULL THEN 1 ELSE NULL', (new SqliteCaseBranches($null, new SqliteCaseArm($one, $null), new SqliteCaseArm($null, $one)))->toString());
    }
}
