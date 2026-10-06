<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;

#[CoversClass(OperatorArity::class)]
#[Small]
final class OperatorArityTest extends TestCase
{
    public function testTypesCountsTheWrittenTypes(): void
    {
        self::assertSame([2, 1, 1, 1], [OperatorArity::Binary->types(), OperatorArity::Prefix->types(), OperatorArity::Postfix->types(), OperatorArity::Incomplete->types()]);
    }
}
