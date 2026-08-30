<?php

declare(strict_types=1);

namespace Tests\Unit\Dialect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\MySqlAlterStatements;
use ZtdQuery\Platform\MySql\Dialect\MySqlStatementOptions;

#[CoversClass(MySqlStatementOptions::class)]
final class MySqlStatementOptionsTest extends TestCase
{
    public function testIsSetReportsAnOptionTheStatementWrote(): void
    {
        $operation = MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255)');

        self::assertTrue((new MySqlStatementOptions())->isSet($operation->options, 'ADD'));
    }

    public function testIsSetIsFalseForAnOptionTheStatementDidNotWrite(): void
    {
        $operation = MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255)');

        self::assertFalse((new MySqlStatementOptions())->isSet($operation->options, 'DROP'));
    }
}
