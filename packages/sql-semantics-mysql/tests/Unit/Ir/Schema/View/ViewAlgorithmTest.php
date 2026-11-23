<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewAlgorithm;

#[CoversClass(ViewAlgorithm::class)]
#[Small]
final class ViewAlgorithmTest extends TestCase
{
    public function testCasesExposeTheCheckedPolicies(): void
    {
        self::assertSame(['UNDEFINED', 'MERGE', 'TEMPTABLE'], array_column(ViewAlgorithm::cases(), 'value'));
    }
}
