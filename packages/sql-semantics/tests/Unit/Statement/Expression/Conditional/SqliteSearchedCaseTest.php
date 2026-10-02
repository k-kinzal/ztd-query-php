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
use SqlSemantics\Statement\Expression\Conditional\SqliteSearchedCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\NullDomain;

#[CoversClass(SqliteSearchedCase::class)]
#[Small]
final class SqliteSearchedCaseTest extends TestCase
{
    public function testTypeRetainsBranchDomains(): void
    {
        $null = new NullConstant();
        self::assertSame(NullDomain::Null, (new SqliteSearchedCase(new SqliteCaseBranches(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new SqliteCaseArm($null, $null))))->type());
    }

    public function testNullabilityIncludesTheImplicitNullFallback(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        self::assertSame(Nullability::MaybeNull, (new SqliteSearchedCase(new SqliteCaseBranches(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new SqliteCaseArm($one, $one))))->nullability());
    }

    public function testReferencesIncludesEveryPotentialLookup(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $column = new ColumnReference($scope, new Name('input'));
        $case = new SqliteSearchedCase(new SqliteCaseBranches(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new SqliteCaseArm($column, $column)));
        self::assertSame([$column, $column], $case->references());
    }

    public function testToStringPreservesTruthTests(): void
    {
        $text = new SqliteText(new StringLiteral('1english'));
        $case = new SqliteSearchedCase(new SqliteCaseBranches(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new SqliteCaseArm($text, $text)));
        self::assertSame("CASE WHEN '1english' THEN '1english' END", $case->toString());
    }
}
