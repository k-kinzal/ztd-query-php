<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\From\JsonDocument;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(JsonDocument::class)]
#[Small]
final class JsonDocumentTest extends TestCase
{
    /**
     * @return iterable<string, array{M\Query\Select, string}>
     */
    public static function providerDocuments(): iterable
    {
        $one = new M\Literal\NumberLiteral('1');
        $query = new M\Query\Select([], [new M\Query\SelectExpression($one)]);
        foreach ([
            'row' => [new M\Expression\Row([$one, new M\Literal\NumberLiteral('2')]), 'Operand should contain 1 column(s), not 2.'],
            'subquery' => [new M\Expression\Subquery\ScalarSubquery($query), 'Incorrect arguments to JSON_TABLE'],
            'tableless-star' => [new M\Routine\Program\Expression\DeferredSubquery(new M\Query\Select([], [new M\Query\Star()])), 'No tables used'],
        ] as $name => [$document, $message]) {
            $table = new M\Relation\JsonTable($document, new M\Literal\StringLiteral(['$']), [new M\Call\Json\OrdinalityColumn(new Name('n'))], new Name('j'));
            yield $name => [new M\Query\Select([], [new M\Query\Star()], $table), $message];
        }
    }

    #[DataProvider('providerDocuments')]
    public function testProblemRetainsTheDocumentConditionUntilItsReadRole(M\Query\Select $query, string $message): void
    {
        self::assertInstanceOf(M\Relation\JsonTable::class, $query->from);
        self::assertSame($message, (new JsonDocument())->problem($query->from)?->message());
        self::assertSame($query, (new M\Routine\Program\Selection\DeferredQuery($query))->query);
    }

    #[DataProvider('providerDocuments')]
    public function testOrdinaryReadRetainsAFixedDocumentContradiction(M\Query\Select $query): void
    {
        self::assertTrue((new M\QueryRoot($query))->hasExecutionCondition());
    }

    #[DataProvider('providerDocuments')]
    public function testAnUnusedCommonTableKeepsTheSameDocumentWithoutActivatingIt(M\Query\Select $query): void
    {
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $query)]);
        $body = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $root = new M\QueryRoot(new M\Query\QueryExpression($with, $body));
        self::assertInstanceOf(M\Query\QueryExpression::class, $root->query);
        self::assertSame($with, $root->query->with);
        self::assertSame($query, $with->tables[0]->query);
        self::assertTrue((new M\QueryRoot(new M\Query\QueryExpression($with, new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c')))))))->hasExecutionCondition());
    }

    public function testProblemAcceptsAScalarDocumentWithoutAQuery(): void
    {
        $table = new M\Relation\JsonTable(new M\Literal\NullLiteral(), new M\Literal\StringLiteral(['$']), [new M\Call\Json\OrdinalityColumn(new Name('n'))], new Name('j'));
        self::assertNull((new JsonDocument())->problem($table));
    }
}
