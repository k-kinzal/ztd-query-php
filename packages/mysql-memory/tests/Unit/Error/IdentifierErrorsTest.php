<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\IdentifierErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(IdentifierErrors::class)]
#[Small]
final class IdentifierErrorsTest extends TestCase
{
    public function testLengthKeepsTheOperandWhileBoundingTheDisplayedDiagnostic(): void
    {
        $name = new Name(str_repeat('猫', 65));
        $problem = new NameTooLong($name, ObjectNameKind::Database);
        $error = (new IdentifierErrors())->length($problem, false);
        self::assertSame(1102, $error->getCode());
        self::assertSame("Incorrect database name '" . str_repeat('猫', 33) . "?'", $error->getMessage());
        self::assertSame(str_repeat('猫', 65), $problem->name->value);
    }

    public function testLengthKeepsTheLegacyByteBoundaryAndTableNamespace(): void
    {
        $error = (new IdentifierErrors())->length(new NameTooLong(new Name(str_repeat('猫', 65)), ObjectNameKind::Table), true);
        self::assertSame(1103, $error->getCode());
        self::assertSame("Incorrect table name '" . str_repeat('猫', 33) . "'", $error->getMessage());
    }

    public function testLengthKeepsIdentifierRefusalsBelowTheByteLimitAndStopsDisplayAtNul(): void
    {
        $error = (new IdentifierErrors())->length(new NameTooLong(new Name("a\0" . str_repeat('x', 100)), ObjectNameKind::Database), false);
        self::assertSame(1059, $error->getCode());
        self::assertSame("Identifier name 'a' is too long", $error->getMessage());
    }
}
