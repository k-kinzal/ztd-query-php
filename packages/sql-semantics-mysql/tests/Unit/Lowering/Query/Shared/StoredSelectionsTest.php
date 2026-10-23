<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredSelections;

#[CoversClass(StoredSelections::class)]
#[Medium]
final class StoredSelectionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.4.7', 'mysql-9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testReadPreservesOrdinarySelectionsAndDeferredOperandRoles(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        self::assertSame('CREATE PROCEDURE p() SELECT 1 AS x FROM DUAL', $semantics->analyze('CREATE PROCEDURE p() SELECT 1 AS x FROM DUAL')->toString());
        self::assertSame('CREATE PROCEDURE p() SELECT (1, 2)', $semantics->analyze('CREATE PROCEDURE p() SELECT (1,2)')->toString());
        self::assertSame('CREATE PROCEDURE p() SELECT 1 ORDER BY 1', $semantics->analyze('CREATE PROCEDURE p() SELECT 1 ORDER BY 1')->toString());
    }

    public function testBodyRetainsTheCompleteCteDefinitionInItsTemplateRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\QueryExpression::class, $operation->statement->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\With::class, $operation->statement->query->with);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $operation->statement->query->with->tables[0]->query);
    }

    public function testDeferredKeepsAGroupPredicateOutsideTheScalarQueryDomain(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT a FROM t HAVING (a,2)')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $create->body);
    }

    public function testBodyRetainsAnUnusedQualifyConditionInsideTheRejectedUpdate(): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        try {
            $semantics->analyze('WITH c AS (SELECT 1 QUALIFY ROW(1,2)) UPDATE t,t SET a=1');
            self::fail('A fixed alias collision cannot publish an ordinary UPDATE.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate::class, $failure->problem);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\With::class, $failure->problem->with);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $failure->problem->with->tables[0]->query);
            $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($semantics->profile()->grammar));
            $failure->problem->render($out);
            self::assertSame('WITH c AS (SELECT 1 QUALIFY ROW(1, 2)) UPDATE t, t SET a = 1', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
        }
    }

    public function testClausePreservesEveryRepresentedClause(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT DISTINCT (a, 2) FROM t WHERE a GROUP BY a HAVING 1 ORDER BY a LIMIT 0', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT DISTINCT (a,2) FROM t WHERE a GROUP BY a HAVING 1 ORDER BY a LIMIT 0')->toString());
    }

    public function testFrontierPreservesClausesOwnedByOtherQueryRules(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1 LIMIT 1', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 LIMIT 1')->toString());
    }

    public function testItemsKeepsTheScalarOccurrenceAndItsObservableLayout(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1+1, (2, 3) AS pair FROM t', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1+1, (2,3) AS pair FROM t')->toString());
    }

    public function testPredicateRetainsItsRowShape(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2, 3)', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2,3)')->toString());
    }
    public function testBlockKeepsItsOpenTailUntilTheOuterClausesAreKnown(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('WITH x AS (SELECT 1) SELECT * FROM x ORDER BY 1 FOR UPDATE');
        self::assertSame('WITH x AS (SELECT 1) SELECT * FROM x ORDER BY 1 FOR UPDATE', $operation->toString());
        self::assertSame($operation->toString(), $semantics->analyze($operation->toString())->toString());
    }

    public function testBodyRetainsAnIntoDestinationAndTheOuterWriteOfADeferredPredicate(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        try {
            $semantics->analyze("REPLACE absent.t SELECT 1 INTO DUMPFILE 'text' WHERE ROW(1,2)");
            self::fail('A row predicate retains the complete deferred write.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery::class, $failure->problem);
            self::assertSame('absent', $failure->problem->write->into->table->name->schema?->value);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $failure->problem->write->source);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile::class, $failure->problem->write->source->selection->into);
        }
    }

    public function testReadRetainsIntoInAStoredDefinitionWithoutAQuerySurrogate(): void
    {
        $sql = 'CREATE PROCEDURE p() SELECT 1 INTO @x WHERE (1, 2)';
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
    }

}
