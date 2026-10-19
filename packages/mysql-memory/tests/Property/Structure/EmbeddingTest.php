<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Embedding;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Reference;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks that free scalar context is identical for direct and native execution.
 */
#[CoversNothing]
#[Small]
final class EmbeddingTest extends TestCase
{
    /**
     * Supplies a lexical window while retaining the original scalar by identity.
     */
    public function testStatementSuppliesTheAmbientWindowWithoutReplacingTheOriginalScalar(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new Name('w'));
        $statement = (new Embedding())->statement($call);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertInstanceOf(M\Query\SelectExpression::class, $statement->query->items[0]);
        self::assertSame($call, $statement->query->items[0]->expression);
        self::assertSame('w', $statement->query->windows[0]->name->value);
        self::assertSame($statement, (new Embedding())->statement($statement));
        $result = (new Execution())->run($call);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        $profile = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->profile();
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql8044, (new \Fuzz\Structure\Profiles())->select($call)->grammar);
        self::assertSame('SELECT ROW_NUMBER() OVER w WINDOW w AS ()', (new Reference())->sql($call, $profile));
    }
}
