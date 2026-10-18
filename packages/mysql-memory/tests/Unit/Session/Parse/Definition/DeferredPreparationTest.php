<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\DeferredPreparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeferredPreparation::class)]
#[Medium]
final class DeferredPreparationTest extends TestCase
{
    public function testRetainsAPreparedDefinitionWithoutReportingASqlError(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(DeferredPreparation::class);
        $this->expectExceptionMessage('The prepared table definition retains an execution-time partition condition.');

        $session->analyze('CREATE TABLE later.t(a INT) PARTITION BY HASH(ROW(1,2))', true);
    }
}
