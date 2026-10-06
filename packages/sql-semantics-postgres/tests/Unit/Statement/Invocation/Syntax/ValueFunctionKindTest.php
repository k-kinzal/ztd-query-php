<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(ValueFunctionKind::class)]
#[Small]
final class ValueFunctionKindTest extends TestCase
{
    public function testPreciseTellsTheTimeFunctions(): void
    {
        self::assertTrue(ValueFunctionKind::Localtime->precise());
        self::assertFalse(ValueFunctionKind::CurrentDate->precise());
    }

    public function testBuiltinAnswersTheDocumentedTypes(): void
    {
        self::assertSame([Builtin::Timetz, Builtin::Name, Builtin::Text], [ValueFunctionKind::CurrentTime->builtin(), ValueFunctionKind::CurrentRole->builtin(), ValueFunctionKind::SystemUser->builtin()]);
    }

    public function testNullableTellsTheFunctionsThatCanBeNull(): void
    {
        self::assertTrue(ValueFunctionKind::CurrentSchema->nullable());
        self::assertFalse(ValueFunctionKind::SessionUser->nullable());
    }
}
