<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Subquery\CachedProjection;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(CachedProjection::class)]
#[Small]
final class CachedProjectionTest extends TestCase
{
    public function testDomainRetainsTheExpressionDomain(): void
    {
        $domain = Domain::integer();
        $value = new CachedProjection(new Constant($domain, null));

        self::assertSame($domain, $value->domain());
    }

    public function testEvaluateRetainsNullOnlyForTheCurrentStatement(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [null]);
        $value = new CachedProjection(new ColumnRead(Domain::integer(), 0));
        $first = $value->evaluate($frame);
        $frame->row = [20];
        $again = $value->evaluate($frame);
        $fresh = $value->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 1.0), [20]));

        self::assertSame([null, null, 20], [$first, $again, $fresh]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyReleases(): iterable
    {
        yield 'MySQL 5.6' => ['5.6.51'];
        yield 'MySQL 5.7' => ['5.7.44'];
    }

    #[DataProvider('providerLegacyReleases')]
    public function testEvaluateKeepsConstantEmptyProjectionWarningsForTheRelease(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query("SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'");
        $result = $session->query('SELECT (SELECT 1/0,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) FROM (SELECT 1 UNION ALL SELECT 2) t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertCount(1, $warnings->rows);
    }

    /**
     * @return iterable<string, array{string, list<list<string>>}>
     */
    public static function providerLegacyComparisons(): iterable
    {
        yield 'integer' => ['1', [['0', '0', '1'], ['0', '0', '0']]];
        yield 'decimal' => ['1.0', [['0', '0', '1'], ['0', '0', '0']]];
        yield 'double' => ['1e0', [['0', '0', '1'], ['1', '0', '0']]];
        yield 'string to number' => ["'1'", [['0', '0', '1'], ['1', '0', '1']]];
        yield 'nonnumeric string' => ["'x'", [['0', '1', '0'], ['1', '1', '0']]];
    }

    /**
     * @param list<list<string>> $expected The first and repeated empty comparison results
     */
    #[DataProvider('providerLegacyComparisons')]
    public function testComparedRetainsVersionedNullStateAndConstantConversions(string $expression, array $expected): void
    {
        $sql = 'SELECT (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (NULL,NULL) n, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (0,NULL) z, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (1,NULL) one FROM (SELECT 1 UNION ALL SELECT 2) t';
        $older = (new Instance('5.6.51'))->connect()->query($sql)[0];
        $later = (new Instance('5.7.44'))->connect()->query($sql)[0];

        self::assertInstanceOf(ResultSet::class, $older);
        self::assertInstanceOf(ResultSet::class, $later);
        self::assertSame($expected, $older->rows);
        self::assertSame($expected, $later->rows);
    }

    public function testComparedDoesNotReplaceTheSourceValueWhenItsComparisonSlotIsCleared(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $value = new CachedProjection(new Constant(Domain::integer(), 1));
        $first = $value->compared($frame, false, Kind::Integer, false);
        $again = $value->compared($frame, true, Kind::Integer, false);

        self::assertSame([1, null, 1], [$first, $again, $value->evaluate($frame)]);
    }
}
