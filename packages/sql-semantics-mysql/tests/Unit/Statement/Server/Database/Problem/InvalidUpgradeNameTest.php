<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InvalidUpgradeName::class)]
#[Small]
final class InvalidUpgradeNameTest extends TestCase
{
    public function testMessageDistinguishesTheMarkerAndComponent(): void
    {
        self::assertSame('Incorrect usage of ALTER DATABASE UPGRADE DATA DIRECTORY NAME and name', (new InvalidUpgradeName(new Name('ordinary')))->message());
        self::assertSame("Incorrect database name '#mysql50#'", (new InvalidUpgradeName(new Name('#mysql50#')))->message());
    }
    public function testIdentifierTooLongPreservesThePriorityOfDirectoryForm(): void
    {
        $long = new InvalidUpgradeName(new Name('#mysql50#' . str_repeat('é', 65)));
        self::assertTrue($long->identifierTooLong());
        self::assertStringContainsString('is too long', $long->message());
        self::assertFalse((new InvalidUpgradeName(new Name('#mysql50#' . str_repeat('a', 184))))->identifierTooLong());
        self::assertFalse((new InvalidUpgradeName(new Name('#mysql50#.' . str_repeat('a', 65))))->identifierTooLong());
        self::assertFalse((new InvalidUpgradeName(new Name(str_repeat('a', 65))))->identifierTooLong());
    }

}
