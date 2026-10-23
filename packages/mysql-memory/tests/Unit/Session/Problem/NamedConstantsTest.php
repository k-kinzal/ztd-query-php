<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Session\Problem\NamedConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(NamedConstants::class)]
#[Small]
final class NamedConstantsTest extends TestCase
{
    public function testFollowingKeepsTheAdditionalConditionOfTheExactValueOccurrence(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT NAME_CONST('x', 'a' COLLATE latin1_bin)");
        $error = SchemaError::CollationCharsetMismatch->error('latin1_bin', 'utf8mb4');
        $result = (new NamedConstants())->following($error, $operation->facts->diagnostics[0], $operation->statement);

        self::assertSame([[1382, "The 'NAME_CONST' syntax is reserved for purposes internal to the MySQL server"]], $result->following);
        self::assertSame($error->getMessage(), $result->getMessage());
        self::assertSame($error, (new NamedConstants())->following($error, $operation->facts->diagnostics[0], $operation->statement, \SqlSemantics\Contract\GrammarRelease::MySql910));
    }

    public function testFollowingDoesNotAttachTheConditionToAnEarlierSeparateExpression(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT 'a' COLLATE latin1_bin, NAME_CONST('x', 'a' COLLATE latin1_bin)");
        $error = SchemaError::CollationCharsetMismatch->error('latin1_bin', 'utf8mb4');

        self::assertSame($error, (new NamedConstants())->following($error, $operation->facts->diagnostics[0], $operation->statement));
    }
}
