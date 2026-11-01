<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\GenerationSearch;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(GenerationSearch::class)]
#[Medium]
final class GenerationSearchTest extends TestCase
{
    public function testErrorPreservesTheMissingColumnAndFollowingSearchCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT MATCH(a) AGAINST('x')", []);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $error = (new GenerationSearch())->error($operation, $operation->statementInformation(), (new Instance('5.7.44'))->connect());
        self::assertSame(1054, $error?->getCode());
        self::assertSame([[1210, 'Incorrect arguments to AGAINST']], $error->following);
    }

    public function testErrorRequiresAStatementConstantSearchOperand(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a TEXT)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $table);
        $operation = $semantics->analyze('SELECT MATCH(a) AGAINST(RAND()) FROM t', [$table]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertSame(1210, (new GenerationSearch())->error($operation, $operation->statementInformation(), (new Instance('5.7.44'))->connect())?->getCode());
    }
}
