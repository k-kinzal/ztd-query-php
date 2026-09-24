<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Text\TextOperations;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TextOperations::class)]
#[Medium]
final class TextOperationsTest extends TestCase
{
    #[TestWith(["SELECT POSITION('a' IN 'cat')", \SqlSemantics\Model\Scalar\Text\Position::class])]
    #[TestWith(["SELECT TRIM('a' FROM 'cat')", \SqlSemantics\Model\Scalar\Text\Trim::class])]
    #[TestWith(["SELECT MATCH title AGAINST ('w') FROM t", \SqlSemantics\Model\Scalar\Text\FullTextSearch::class])]
    #[TestWith(["SELECT LOWER('a')", \SqlSemantics\Model\Scalar\Function\FunctionCall::class])]
    #[TestWith(["SELECT NORMALIZE('a')", \SqlSemantics\Model\Scalar\Text\Normalization::class, Dialect::PostgreSql])]
    public function testBindDispatchesEachKeywordStringOperation(string $sql, string $class, Dialect $dialect = Dialect::MySql): void
    {
        $query = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t (title TEXT)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($class, $query->outputs[0]->expression::class);
    }
}
