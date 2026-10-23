<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ArgumentDomainMismatch::class)]
#[Small]
final class ArgumentDomainMismatchTest extends TestCase
{
    public function testMessagePreservesTheResolvedArgumentRoleAndFunctionName(): void
    {
        self::assertSame('Incorrect type for argument SRID in function st_pointfromgeohash.', (new ArgumentDomainMismatch(new Name('st_pointfromgeohash'), 'SRID'))->message());
    }
}
