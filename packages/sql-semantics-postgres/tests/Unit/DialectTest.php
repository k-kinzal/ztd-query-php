<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Dialect;

#[CoversClass(Dialect::class)]
#[Small]
final class DialectTest extends TestCase
{
    public function testDatabaseNamesTheFamilyOfTheGrammarReleases(): void
    {
        self::assertSame('postgresql', Dialect::PostgreSql->database());
    }
}
