<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\TransactionStatementParser;

#[CoversNothing]
final class TransactionStatementParserTest extends TestCase
{
    public function testDefinesDialectParserContract(): void
    {
        $method = new \ReflectionMethod(TransactionStatementParser::class, 'parse');

        self::assertTrue($method->isPublic());
        self::assertSame('sql', $method->getParameters()[0]->getName());
    }
}
