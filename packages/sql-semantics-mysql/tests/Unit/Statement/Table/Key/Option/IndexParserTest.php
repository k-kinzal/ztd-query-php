<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;

#[CoversClass(IndexParser::class)]
#[Medium]
final class IndexParserTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT, FULLTEXT (a) WITH PARSER ngram)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $index = $statement->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class, $index);
        $option = $index->options[0];

        self::assertInstanceOf(IndexParser::class, $option);
        self::assertSame('ngram', $option->parser->value);
        self::assertSame('CREATE TABLE t (a TEXT, FULLTEXT (a) WITH PARSER ngram)', $create->toString());
    }

    /**
     * @return iterable<string, array{list<string>|null, bool}>
     */
    public static function providerCatalogs(): iterable
    {
        yield 'unspecified' => [null, false];
        yield 'empty' => [[], true];
        yield 'different plugin' => [['mecab'], true];
        yield 'case insensitive' => [['NGraM'], false];
    }

    /**
     * @param list<string>|null $catalog The available plugins, or an unspecified catalog
     */
    #[DataProvider('providerCatalogs')]
    public function testProblemUsesTheSuppliedCatalog(?array $catalog, bool $missing): void
    {
        $option = new IndexParser(new \SqlSemantics\Statement\Identifier\Name('ngram'));
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_bin'), fullTextParsers: $catalog === null ? null : array_map(static fn (string $name): \SqlSemantics\Statement\Identifier\Name => new \SqlSemantics\Statement\Identifier\Name($name), $catalog));
        $problem = $option->problem($settings);

        self::assertSame($missing, $problem !== null);
        self::assertSame($missing ? "Function 'ngram' is not defined" : null, $problem?->message());
    }

}
