<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(WindowDefinition::class)]
#[Medium]
final class WindowDefinitionTest extends TestCase
{
    public function testReadsTheNameAndTheSpecification(): void
    {
        $semantics = new Inspection();
        $create = $semantics->inspect('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->inspect('SELECT rank() OVER w FROM t WINDOW w AS (ORDER BY a), v AS (w PARTITION BY b)', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertCount(2, $statement->windows);
        self::assertSame('w', $statement->windows[0]->name->value);
        self::assertCount(1, $statement->windows[0]->window->order);
        self::assertSame('v', $statement->windows[1]->name->value);
        self::assertSame('w', $statement->windows[1]->window->base?->value);
        self::assertCount(1, $statement->windows[1]->window->partition);
        self::assertTrue($operation->facts->covers($statement->windows[0]->window->order[0]->expression));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheNameAndTheParenthesizedSpecification(): void
    {
        $operation = (new Inspection())->inspect('select rank() over w AS c1 from t window w as (order by a), v as (), u as (w partition by b rows unbounded preceding)');

        self::assertSame('SELECT rank() OVER w AS c1 FROM t WINDOW w AS (ORDER BY a), v AS (), u AS (w PARTITION BY b ROWS UNBOUNDED PRECEDING)', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltDefinition(): void
    {
        $semantics = new Inspection();
        $definition = new WindowDefinition(new Name('w'), new WindowSpec(new Name('v'), [new IntegerLiteral('1')]));
        $operation = new InspectedStatement($semantics->context(), new Select([new ResultColumn(new FunctionCall(new Name('rank'), [], false, null, [], null, new Name('w')))], null, null, [], null, [$definition]));

        self::assertSame('SELECT rank() OVER w WINDOW w AS (v PARTITION BY 1)', $operation->toString());
    }
}
