<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\PhpStanCustomRules\Support\ContractSymbolUseIndex;

#[CoversClass(ContractSymbolUseIndex::class)]
final class ContractSymbolUseIndexTest extends TestCase
{
    public function testLoadCollectsOnlyExplicitNonContractImports(): void
    {
        $index = new ContractSymbolUseIndex();
        $imports = $index->load(__DIR__ . '/../../Fixture/ContractUsagePackage');

        self::assertArrayHasKey('SqlFaker\\Contract\\UsedRuntime', $imports);
        self::assertArrayHasKey('SqlFaker\\Contract\\used_helper', $imports);
        self::assertArrayNotHasKey('SqlFaker\\Contract\\UnusedRuntime', $imports);
        self::assertArrayNotHasKey('SqlFaker\\Contract\\unused_helper', $imports);
    }
}
