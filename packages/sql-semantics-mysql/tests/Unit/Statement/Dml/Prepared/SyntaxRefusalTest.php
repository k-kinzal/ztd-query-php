<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal;

#[CoversClass(SyntaxRefusal::class)]
#[Small]
final class SyntaxRefusalTest extends TestCase
{
    public function testRefusalRetainsOnlyTheDiagnosticExcerptAndPosition(): void
    {
        $refusal = new SyntaxRefusal(12, 2, 'FROM');
        self::assertSame([12, 2, 'FROM'], [$refusal->offset, $refusal->line, $refusal->near]);
    }

}
