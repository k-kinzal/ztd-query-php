<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\DefaultExpression as Subject;

#[CoversClass(Subject::class)]
final class DefaultExpressionTest extends TestCase
{
    public function testExtractDefaultReadsNativeDefaultOptions(): void
    {
        $sql = 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY, amount DECIMAL(8, 2) UNSIGNED DEFAULT 12.5)';
        $parser = new \PhpMyAdmin\SqlParser\Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        self::assertIsArray($statement->fields);
        self::assertSame(12.5, (new Subject())->extractDefault($statement->fields[1]->options));
        self::assertNull((new Subject())->extractDefault(null));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDefaultValues')]
    public function testExtractDefaultDistinguishesLiteralsFromSqlExpressions(string $input, int|float|bool|string|null $expected): void
    {
        $options = new \PhpMyAdmin\SqlParser\Components\OptionsArray([['name' => 'DEFAULT', 'value' => $input]]);
        self::assertSame($expected, (new Subject())->extractDefault($options));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerDefaultValues(): array
    {
        return [
            ["'line\nbreak'", "line\nbreak"],
            ["(concat('a','b'))", "(concat('a','b'))"],
            ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ['false', false],
            ['NULL', null],
        ];
    }
}
