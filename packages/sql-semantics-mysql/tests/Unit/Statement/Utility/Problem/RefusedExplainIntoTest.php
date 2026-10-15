<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RefusedExplainInto::class)]
#[Small]
final class RefusedExplainIntoTest extends TestCase
{
    public function testMessageDistinguishesFormatAndAnalyzeRefusals(): void
    {
        self::assertSame('EXPLAIN INTO requires FORMAT=JSON', (new RefusedExplainInto())->message());
        self::assertSame('EXPLAIN INTO requires FORMAT=JSON', (new RefusedExplainInto(new Name('TREE')))->message());
        self::assertSame('EXPLAIN ANALYZE does not support the INTO clause.', (new RefusedExplainInto(new Name('JSON'), true))->message());
    }

    public function testRefusesACompatibleFormatWithoutAnalyze(): void
    {
        $this->expectExceptionMessage('A JSON INTO format has no format refusal.');
        new RefusedExplainInto(new Name('json'));
    }
}
