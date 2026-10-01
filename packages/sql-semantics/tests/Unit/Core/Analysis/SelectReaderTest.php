<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\SelectReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SelectReaderTest extends TestCase
{
    public function testReadRejectsAClauseThatHasNoSemanticModel(): void
    {
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        SemanticCases::select(Dialect::Sqlite, 'SELECT foo FROM bar GROUP BY foo');
    }
}
