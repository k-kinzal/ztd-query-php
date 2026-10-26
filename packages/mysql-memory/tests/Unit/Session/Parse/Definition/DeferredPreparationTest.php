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
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectException(DeferredPreparation::class);
        $this->expectExceptionMessage('The prepared instruction retains a condition resolved during execution.');

        $session->analyze('CREATE INDEX i ON t((ROW(1,2)))', true);
    }
}
