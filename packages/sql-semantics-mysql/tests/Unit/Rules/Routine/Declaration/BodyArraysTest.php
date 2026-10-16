<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyArrays;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(BodyArrays::class)]
#[Small]
final class BodyArraysTest extends TestCase
{
    public function testCheckKeepsScalarCastsAndOpaqueCode(): void
    {
        $rules = new BodyArrays();
        $rules->check(new ReturnStatement(new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Signed))));
        $rules->check(new ExternalBody(new Text('CAST(1 AS SIGNED ARRAY)')));
        self::assertNull($rules->problem(new ReturnStatement(new StringLiteral(['CAST(1 AS SIGNED ARRAY)']))));
    }

    public function testProblemRejectsAnArrayKeyInAStoredInstruction(): void
    {
        $key = new \SqlSemantics\Platform\MySql\Statement\Table\CreateIndex(new \SqlSemantics\Statement\Identifier\Name('k'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart(new NumberLiteral('1'), new CastTarget(CastKind::Signed))]);
        self::assertSame(ProgramRule::ArrayCast, (new BodyArrays())->problem($key)?->rule);
    }

    public function testProblemInspectsUnreachableOperandsWithoutExecutingThem(): void
    {
        $body = new Block(statements: [new IfStatement([new ConditionalBranch(new NumberLiteral('0'), [new ReturnStatement(new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Unsigned), true))])])]);

        self::assertSame(ProgramRule::ArrayCast, (new BodyArrays())->problem($body)?->rule);
    }
}
