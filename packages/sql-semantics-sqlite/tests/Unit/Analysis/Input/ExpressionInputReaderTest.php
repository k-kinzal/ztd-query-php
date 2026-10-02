<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input as I;
use SqlSemantics\Statement\Construction as C;

#[CoversClass(I\ExpressionInputReader::class)]
#[Small]
final class ExpressionInputReaderTest extends TestCase
{
    public function testReadPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1+2')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->read($source);
        self::assertSame('1+2', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testReferencePreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT x.id')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->reference($source);
        self::assertSame('x.id', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testInfixPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1-2')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->infix($source);
        self::assertNotNull($input);
        self::assertSame('1-2', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testPredicatePreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT 1 NOT IN (2, 3)')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->predicate($source);
        self::assertNotNull($input);
        self::assertSame('(1) NOT IN (2, 3)', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testColumnPreservesTheSpecifiedOperation(): void
    {
        $source = (new SqliteParser())->parse('SELECT x.id')->find('expr')[0];
        $input = (new I\ExpressionInputReader())->column($source);
        self::assertSame('x.id', (new C\Rendering\ExpressionSql())->write($input));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnarySpellings')]
    public function testReadKeepsUnaryOutputNamesWithoutAddingCapturingAliases(string $expression): void
    {
        $quoted = '"' . str_replace('"', '""', $expression) . '"';
        $sql = 'SELECT ' . $expression . ', 0 AS ' . $quoted . ' WHERE ' . $quoted;
        $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')));
        $query = (new \SqlSemantics\Platform\Sqlite\Analysis\SelectReader())->read((new SqliteParser())->parse($sql)->find('select')[0], $catalog);
        self::assertNull($query->fields()->items[0]->alias);
        self::assertSame($expression, $query->fields()->items[0]->name->value);
        self::assertInstanceOf(\SqlSemantics\Statement\Projection\AliasReference::class, $query->where);
        self::assertSame($query->fields()->items[1], $query->where->field);
        $database = new PDO('sqlite::memory:');
        $original = $database->query($sql);
        $rebuilt = $database->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame([], $original->fetchAll(PDO::FETCH_NUM));
        self::assertSame([], $rebuilt->fetchAll(PDO::FETCH_NUM));
        self::assertSame($original->getColumnMeta(0), $rebuilt->getColumnMeta(0));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerUnarySpellings(): array
    {
        return [['-1'], ['+1'], ['~0'], ['not 0'], ['not/*operand*/0'], ['not not 1'], ['-(+1)'], ['- -1']];
    }
}
