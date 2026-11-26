<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\MySql\Rules\Query\ExpressionFacts;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ExpressionFacts::class)]
#[Medium]
final class ExpressionFactsTest extends TestCase
{
    public function testDeriveResolvesTheOrderingAgainstTheOutputAndBindsTheTables(): void
    {
        $operation = (new Inspection())->diagnose('WITH c AS (SELECT 1 AS x) (SELECT x FROM c) ORDER BY 1 LIMIT 1', []);

        self::assertInstanceOf(QueryExpression::class, $operation->statement);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($operation->statement->orderBy[0]->expression)->resolution);
        self::assertSame($operation->facts->query($operation->statement->body), $operation->facts->output);
    }

    public function testOrderingSeesTheOutputColumnsButNoTableOfTheQuery(): void
    {
        $semantics = new Inspection('mysql-5.6.51');
        $table = $semantics->diagnose('CREATE TABLE t (a INT NOT NULL)');
        $named = $semantics->diagnose('SELECT (SELECT a AS x FROM t UNION SELECT 2 FROM DUAL LIMIT 1 ORDER BY x)', [$table]);
        $qualified = $semantics->diagnose('SELECT (SELECT a AS x FROM t UNION SELECT 2 FROM DUAL LIMIT 1 ORDER BY t.a)', [$table]);

        self::assertSame([], $named->facts->diagnostics);
        self::assertCount(1, $qualified->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $qualified->facts->diagnostics[0]);
        self::assertSame('a', $qualified->facts->diagnostics[0]->name->value);
    }
}
