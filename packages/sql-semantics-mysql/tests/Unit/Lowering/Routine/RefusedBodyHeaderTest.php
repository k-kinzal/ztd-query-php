<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Routine\RefusedBodyHeader;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;

#[CoversClass(RefusedBodyHeader::class)]
#[Medium]
final class RefusedBodyHeaderTest extends TestCase
{
    public function testWrapRetainsTheParameterHeaderBeforeTheArrayRefusal(): void
    {
        $this->expectException(RejectedInput::class);
        $this->expectExceptionMessage('Incorrect usage of CAST( .. AS .. ARRAY) and stored routines');

        (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE d.p(a INT) SELECT CAST(a AS SIGNED ARRAY)');
    }

    public function testRoutineRetainsFunctionTypesAndCompletionBoundaries(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION d.f(a INT) RETURNS INT RETURN CAST(a AS SIGNED ARRAY)');
            self::fail('A stored function cannot retain a multi-valued cast.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedProgramBody::class, $failure->problem);
            self::assertSame('d', $failure->problem->name->schema?->value);
            self::assertNotNull($failure->problem->returns);
            self::assertCount(1, $failure->problem->parameters->parameters ?? []);
            self::assertCount(2, $failure->problem->typeEnds);
        }
    }
}
