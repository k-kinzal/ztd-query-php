<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
use SqlSemantics\Schema\Constraint\ConflictClause;

#[CoversClass(ConflictClause::class)]
#[Medium]
final class ConflictClauseTest extends TestCase
{
    #[TestWith([ConstraintResponse::Replace, Dialect::Sqlite])]
    #[TestWith([ConstraintResponse::Default, Dialect::MySql])]
    #[TestWith([ConstraintResponse::Default, Dialect::PostgreSql])]
    public function testCheckAcceptsSqliteResolutionsAndAnAbsentOne(ConstraintResponse $resolution, Dialect $dialect): void
    {
        $this->expectNotToPerformAssertions();
        ConflictClause::check($resolution, $dialect);
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::PostgreSql])]
    public function testCheckRejectsAResolutionOutsideSqlite(Dialect $dialect): void
    {
        $this->expectException(InvalidStructure::class);
        ConflictClause::check(ConstraintResponse::Ignore, $dialect);
    }
}
