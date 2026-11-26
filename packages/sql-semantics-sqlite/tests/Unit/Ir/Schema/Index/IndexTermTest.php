<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexTerm;

#[CoversClass(IndexTerm::class)]
#[Small]
final class IndexTermTest extends TestCase
{
    public function testRetainsTheOrderedColumnOperand(): void
    {
        $operand = new ColumnReference(2);
        $term = new IndexTerm($operand, true);

        self::assertSame($operand, $term->expression);
        self::assertTrue($term->descending);
    }

    public function testRejectsAnUnresolvedNamePosition(): void
    {
        $this->expectExceptionMessage('index name position');

        new IndexTerm(new TextLiteral('some field'));
    }
}
