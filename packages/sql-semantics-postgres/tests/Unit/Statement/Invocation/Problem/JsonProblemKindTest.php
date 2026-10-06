<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind;

#[CoversClass(JsonProblemKind::class)]
#[Small]
final class JsonProblemKindTest extends TestCase
{
    public function testCasesCarryTheServerMessages(): void
    {
        self::assertSame('subquery must return only one column', JsonProblemKind::SubqueryColumns->value);
    }
}
