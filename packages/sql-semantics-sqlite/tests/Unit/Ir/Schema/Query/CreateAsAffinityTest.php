<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateAsAffinity;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration;

#[CoversClass(CreateAsAffinity::class)]
#[Small]
final class CreateAsAffinityTest extends TestCase
{
    public function testAffinityLooksThroughCollation(): void
    {
        $cast = new CastExpression(CastTarget::Integer, new TextLiteral('001'));
        $value = new CollatedExpression($cast, Collation::NoCase);

        self::assertSame(CastTarget::Integer, (new CreateAsAffinity())->affinity($value));
    }

    public function testDeclaredUsesTheNativeAffinitySpelling(): void
    {
        self::assertSame(CreatedColumnDeclaration::Numeric, (new CreateAsAffinity())->declared(CastTarget::Numeric));
    }

    public function testCompoundSelectsNumericForLeadingBareNumericCast(): void
    {
        $cast = new CastExpression(CastTarget::Real, new TextLiteral('1.0'));

        self::assertSame(CreatedColumnDeclaration::Numeric, (new CreateAsAffinity())->compound([$cast, new IntegerLiteral('7')]));
    }

    public function testDataTypesTreatsTheTypeOfAParameterAsOpenAtEvaluation(): void
    {
        self::assertSame(7, (new CreateAsAffinity())->dataTypes(new Parameter('2')));
    }

    public function testLeafTypesIncludesTheNumericCategoryForAnInteger(): void
    {
        self::assertSame(1, (new CreateAsAffinity())->leafTypes(new IntegerLiteral('7')));
    }

    public function testCaseTypesCombinesTheValueRolesOfEveryBranch(): void
    {
        $branch = new CaseBranch(new IntegerLiteral('1'), new TextLiteral('selected'));
        $case = new CaseExpression(null, [$branch], new IntegerLiteral('7'));

        self::assertSame(3, (new CreateAsAffinity())->caseTypes($case));
    }
}
