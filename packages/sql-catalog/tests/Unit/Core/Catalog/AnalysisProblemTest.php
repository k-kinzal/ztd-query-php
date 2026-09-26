<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\AnalysisProblem;

#[CoversClass(AnalysisProblem::class)]
final class AnalysisProblemTest extends TestCase
{
    public function testKeepsTheFileAndWhyItCouldNotBeRead(): void
    {
        $problem = new AnalysisProblem('src/a.php', 'unexpected token');
        self::assertSame('src/a.php', $problem->file);
        self::assertSame('unexpected token', $problem->message);
    }
}
