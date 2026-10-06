<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key\Option;

use PHPUnit\Framework\Attributes\CoversClass;
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
}
