<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\ValueRows;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ValueRows::class)]
#[Medium]
final class ValueRowsTest extends TestCase
{
    public function testCheckRejectsRowsOfDifferentWidths(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ValuesWidth->message());
        ValueRows::check([[1, 2], [3]], new Node('values', 0, [new Token(0, 'ID', 'x', 0)]));
    }

    public function testCheckAcceptsUniformRows(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('VALUES (1, 2), (3, 4)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\ValuesStatement::class, $statement);
        self::assertCount(2, $statement->rows);
        self::assertSame('VALUES (1, 2), (3, 4)', $statement->toString());
    }

    public function testCheckIsAppliedWhileBindingValues(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ValuesWidth->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('VALUES (1, 2), (3)');
    }

    /**
     * @param list<list<string>> $expected
     */
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t VALUES (1, 2), (3, 4)', [['1', '2'], ['3', '4']]])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t VALUES (1, DEFAULT), (3, 4)', [['1', 'DEFAULT'], ['3', '4']]])]
    #[TestWith([Dialect::Sqlite, 'INSERT INTO t VALUES (1, 2)', [['1', '2']]])]
    public function testNodesCollectsEachRowsValueNodesAcrossDialects(Dialect $dialect, string $sql, array $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql);
        $rows = ValueRows::nodes($statement->origin->source);
        self::assertSame($expected, array_map(static fn (array $row): array => array_map(static fn (Node $value): string => trim($value->toString()), $row), $rows));
    }
}
