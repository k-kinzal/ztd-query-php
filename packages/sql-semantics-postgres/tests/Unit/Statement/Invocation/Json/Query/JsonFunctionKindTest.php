<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(JsonFunctionKind::class)]
#[Small]
final class JsonFunctionKindTest extends TestCase
{
    public function testBuiltinAnswersTheDefaultResultTypes(): void
    {
        self::assertSame([Builtin::Bool, Builtin::Jsonb, Builtin::Text], [JsonFunctionKind::Exists->builtin(), JsonFunctionKind::Query->builtin(), JsonFunctionKind::Value->builtin()]);
    }
}
