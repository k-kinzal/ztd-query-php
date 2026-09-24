<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Binding\Statement\UnclassifiedSql;

#[CoversClass(UnclassifiedSql::class)]
final class UnclassifiedSqlTest extends TestCase
{
    public function testItCarriesTheClassificationMessageWithoutACodeOrCause(): void
    {
        $error = new UnclassifiedSql('Unclassified statement: X');
        self::assertSame('Unclassified statement: X', $error->getMessage());
        self::assertSame(0, $error->getCode());
        self::assertNull($error->getPrevious());
    }

    public function testItKeepsTheCauseItIsRaisedWith(): void
    {
        $cause = new RuntimeException('parse');
        $error = new UnclassifiedSql('Unclassified statement: X', 7, $cause);
        self::assertSame($cause, $error->getPrevious());
        self::assertSame(7, $error->getCode());
    }
}
