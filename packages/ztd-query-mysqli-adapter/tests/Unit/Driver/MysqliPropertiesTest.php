<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqli;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliProperties;

#[CoversClass(MysqliProperties::class)]
final class MysqliPropertiesTest extends TestCase
{
    public function testNamedAnswersAPropertyTheDriverHoldsWithoutBeingConnected(): void
    {
        $properties = new MysqliProperties(new StubMysqli());

        self::assertIsString($properties->named('client_info'));
    }

    public function testNamedAnswersTheClientVersionAsTheDriverNumbersIt(): void
    {
        $properties = new MysqliProperties(new StubMysqli());

        self::assertIsInt($properties->named('client_version'));
    }

    public function testNamedAnswersNothingForANameMysqliHasNoPropertyUnder(): void
    {
        $properties = new MysqliProperties(new StubMysqli());

        self::assertNull($properties->named('no_such_property'));
    }
    public function testNamedAnswersNothingWhereTheNameIsOneMysqliDoesNotHold(): void
    {
        $properties = new MysqliProperties(new StubMysqli());

        self::assertNull($properties->named('affected_rows_typo'));
    }
}
