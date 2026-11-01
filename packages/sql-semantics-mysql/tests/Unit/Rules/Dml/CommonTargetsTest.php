<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\CommonTargets;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CommonTargets::class)]
#[Small]
final class CommonTargetsTest extends TestCase
{
    public function testContainsLeavesCaseDistinctAndQualifiedTablesToBinding(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $query)]);
        $rules = new CommonTargets();
        self::assertTrue($rules->contains($with, new QualifiedName(new Name('c'))));
        self::assertFalse($rules->contains($with, new QualifiedName(new Name('C'))));
        self::assertFalse($rules->contains($with, new QualifiedName(new Name('c'), new Name('d'))));
        self::assertFalse($rules->contains(null, new QualifiedName(new Name('c'))));
    }
}
