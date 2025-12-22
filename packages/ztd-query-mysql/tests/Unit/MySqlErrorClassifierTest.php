<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\MySql\MySqlErrorClassifier;

#[CoversClass(MySqlErrorClassifier::class)]
final class MySqlErrorClassifierTest extends TestCase
{
    public function testUnknownColumnErrorIsClassifiedAsUnknownSchema(): void
    {
        $classifier = new MySqlErrorClassifier();
        $exception = new DatabaseException('Unknown column', 1054);
        self::assertTrue($classifier->isUnknownSchemaError($exception));
    }

    public function testTableNotExistsErrorIsClassifiedAsUnknownSchema(): void
    {
        $classifier = new MySqlErrorClassifier();
        $exception = new DatabaseException('Table does not exist', 1146);
        self::assertTrue($classifier->isUnknownSchemaError($exception));
    }

    public function testUndeclaredVariableErrorIsClassifiedAsUnknownSchema(): void
    {
        $classifier = new MySqlErrorClassifier();
        $exception = new DatabaseException('Undeclared variable', 1327);
        self::assertTrue($classifier->isUnknownSchemaError($exception));
    }

    public function testNonSchemaErrorIsNotClassifiedAsUnknownSchema(): void
    {
        $classifier = new MySqlErrorClassifier();
        $exception = new DatabaseException('Syntax error', 1064);
        self::assertFalse($classifier->isUnknownSchemaError($exception));
    }

    public function testNullDriverCodeReturnsFalse(): void
    {
        $classifier = new MySqlErrorClassifier();
        $exception = new DatabaseException('Unknown error', null);
        self::assertFalse($classifier->isUnknownSchemaError($exception));
    }
}
