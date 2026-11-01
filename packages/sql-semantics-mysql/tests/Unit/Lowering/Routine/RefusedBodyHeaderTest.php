<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Routine\RefusedBodyHeader;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;

#[CoversClass(RefusedBodyHeader::class)]
#[Medium]
final class RefusedBodyHeaderTest extends TestCase
{
    public function testWrapRetainsTheParameterHeaderBeforeAnInputRefusal(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $sql = 'CREATE PROCEDURE d.p(a INT) SELECT CAST(a AS SIGNED ARRAY)';
        $failure = new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::ArrayCast), strlen($sql));
        $result = (new RefusedBodyHeader())->wrap($platform->parser($profile)->parse($sql), $failure, $lowering);

        self::assertInstanceOf(RefusedProgramBody::class, $result->problem);
        self::assertSame('d', $result->problem->name->schema?->value);
        self::assertCount(1, $result->problem->parameters->parameters ?? []);
        self::assertSame($failure->problem, $result->problem->problem);
    }

    public function testRoutineRetainsFunctionTypesAndCompletionBoundaries(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $sql = 'CREATE FUNCTION d.f(a INT) RETURNS INT RETURN CAST(a AS SIGNED ARRAY)';
        $failure = new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::ArrayCast), strlen($sql));
        $result = (new RefusedBodyHeader())->routine($platform->parser($profile)->parse($sql)->find('sf_tail')[0], $failure, $lowering, null);

        self::assertInstanceOf(RefusedProgramBody::class, $result->problem);
        self::assertSame('d', $result->problem->name->schema?->value);
        self::assertNotNull($result->problem->returns);
        self::assertCount(1, $result->problem->parameters->parameters ?? []);
        self::assertCount(2, $result->problem->typeEnds);
    }
}
