<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Problem\Location\Owned;
use MySqlMemory\Session\Problem\Locations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;

#[CoversClass(Owned::class)]
#[Medium]
final class OwnedTest extends TestCase
{
    public function testLocatedJsonPathFollowsItsDocumentButPrecedesTheNextExpression(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT JSON_VALUE(CAST(missing_function() AS SIGNED), ''), JSON_VALUE(0, '$')");
        $locator = (new Locator())->statement($operation->statement);
        $calls = (new \MySqlMemory\Session\Problems())->calls($operation);
        [$names] = (new Locations())->names($operation, $locator, $calls, $operation->facts->diagnostics);
        $located = (new Owned())->located($locator, $operation->facts->diagnostics, $names);

        self::assertCount(3, $located);
        $first = Locations::first($located);
        self::assertNotNull($first);
        self::assertSame($calls[0], $first[0]);
        unset($located[spl_object_id($calls[0])]);
        $next = Locations::first($located);
        self::assertNotNull($next);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath::class, $next[0]);
    }

    public function testLocatedConversionFollowsItsOperandButPrecedesTheNextExpression(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $operation = $semantics->analyze("SELECT CAST(a AT TIME ZONE '+00:00' AS DATETIME), missing FROM t", [$table]);
        $locator = (new Locator())->statement($operation->statement);
        [$names] = (new Locations())->names($operation, $locator, [], $operation->facts->diagnostics);
        $located = (new Owned())->located($locator, $operation->facts->diagnostics, $names);
        $first = Locations::first($located);

        self::assertNotNull($first);
        self::assertInstanceOf(TimeZoneConversion::class, $first[0]);
        self::assertTrue($first[0]->operand);
        self::assertCount(2, $located);
    }
}
