<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Execution\DoExpressionsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\DiscardedResults;

#[CoversClass(DiscardedResults::class)]
#[Medium]
final class DiscardedResultsTest extends TestCase
{
    public function testWriteRetainsExpressionPrecedenceAndLiteralBoundaries(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DO (1 + 2) * 3, 'a; DO 9'");
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        self::assertSame("DO((1 + 2) * 3), 'a; DO 9'", DiscardedResults::write($statement)->toString());
    }

}
