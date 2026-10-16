<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Prepared\BranchParameters;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\ParameterInference;

#[CoversClass(BranchParameters::class)]
#[Small]
final class BranchParametersTest extends TestCase
{
    public function testOperandsExcludeTheCasePredicate(): void
    {
        $result = new NumberLiteral('1');
        $fallback = new Parameter();
        $case = new CaseExpression(null, [new CaseBranch(new Parameter(), $result)], $fallback);

        self::assertSame([$result, $fallback], (new BranchParameters())->operands($case));
        self::assertSame([], (new BranchParameters())->operands($result));
    }

    public function testInferUsesResolvedSlotsAndOtherBranches(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT CASE WHEN ? THEN 123 ELSE ? END, COALESCE('é', ?), IF(?, NULL, ?), IFNULL(NULL, ?)");
        $domains = ParameterInference::infer($operation);

        self::assertSame([0, 1, 2, 3, 4, 5], array_keys($domains));
        self::assertSame([Kind::String, Kind::Integer, Kind::String, Kind::String, Kind::String, Kind::String], array_map(static fn (array $entry): Kind => $entry[0]->kind, $domains));
        self::assertSame([16383, 21, 16383, 16383, 65535, 65535], array_map(static fn (array $entry): int => $entry[0]->length, $domains));
        self::assertTrue($domains[4][0]->collation->bytes());
    }
}
