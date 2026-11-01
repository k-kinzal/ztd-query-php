<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\WeightInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeightInput::class)]
#[Medium]
final class WeightInputTest extends TestCase
{
    public function testBeforeRetainsTheEarlierConstructorDomainRefusal(): void
    {
        $sql = 'SELECT WEIGHT_STRING(NULL AS BINARY(00000)) trailing garbage';
        $session = (new Instance())->connect();
        $failure = (new WeightInput())->before($sql, strlen($sql), $session);

        self::assertNotNull($failure);
        self::assertSame(1064, $failure->getCode());
        self::assertStringContainsString("near '00000)) trailing garbage'", $failure->getMessage());
        self::assertNull((new WeightInput())->before($sql, 10, $session));
    }

    public function testBeforeLeavesPositiveAndUnrelatedOperandsAlone(): void
    {
        $session = (new Instance())->connect();

        self::assertNull((new WeightInput())->before('SELECT WEIGHT_STRING(NULL AS CHAR(1)) broken syntax', 100, $session));
        self::assertNull((new WeightInput())->before('SELECT WEIGHT_STRING(NULL AS CHAR(1.0)) broken syntax', 100, $session));
        self::assertNull((new WeightInput())->before('SELECT CAST(NULL AS CHAR(0)) broken syntax', 100, $session));
    }
}
