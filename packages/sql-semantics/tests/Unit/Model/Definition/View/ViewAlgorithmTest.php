<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;

#[CoversClass(ViewAlgorithm::class)]
final class ViewAlgorithmTest extends TestCase
{
    public function testRepresentsEveryMySqlViewAlgorithm(): void
    {
        self::assertSame(['UNDEFINED', 'MERGE', 'TEMPTABLE'], array_column(ViewAlgorithm::cases(), 'value'));
    }

}
