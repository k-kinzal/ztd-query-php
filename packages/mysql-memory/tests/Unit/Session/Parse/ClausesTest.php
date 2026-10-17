<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Clauses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Clauses::class)]
#[Small]
final class ClausesTest extends TestCase
{
    public function testUnitedAnswersTheFirstUnionAfterAnInto(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT 1 UNION SELECT 2 INTO @a UNION SELECT 3');

        self::assertSame(32, (new Clauses())->united($tokens));
    }

    public function testUnitedAnswersTheFirstUnionAfterALimit(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT 1 LIMIT 1 UNION SELECT 2');

        self::assertSame(17, (new Clauses())->united($tokens, 'LIMIT'));
    }

    public function testClosingAnswersTheEndOfTheParenthesesAroundAnOffset(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT * FROM (t UNION SELECT 1) AS q');

        self::assertSame([31, 34], [(new Clauses())->closing($tokens, 17), (new Clauses())->closing($tokens, 34)]);
    }

    public function testViewedAnswersTheOffsetOfIntoInTheQueryOfAView(): void
    {
        $parser = (new Instance('5.7.44'))->connect()->semantics()->parser();

        self::assertSame([26, null], [(new Clauses())->viewed($parser->tokenize('CREATE VIEW v AS SELECT 1 INTO @a')), (new Clauses())->viewed($parser->tokenize('SELECT 1 INTO @a'))]);
    }

    public function testMarkedAnswersWhetherTheQueryWritesAVariableBeforeAnOffset(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize("CREATE DEFINER = 'u'@'h' VIEW v AS SELECT @x INTO @a");

        self::assertSame([true, false], [(new Clauses())->marked($tokens, 47), (new Clauses())->marked($tokens, 40)]);
    }

    public function testUnknownAnswersAnUndeclaredVariableOfALimitBeforeAnOffset(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT 1 INTO @a LIMIT 1, b UNION SELECT 2');

        self::assertSame(['b', null], [(new Clauses())->unknown($tokens, 100, null), (new Clauses())->unknown($tokens, 25, null)]);
    }

    public function testClauseAnswersTheClauseNamingVariablesATokenIsPartOf(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT 1 INTO a LIMIT b');
        $clauses = new Clauses();

        self::assertSame(['INTO', 'INTO', 'LIMIT', null], [$clauses->clause(null, $tokens[2], $tokens[1], true), $clauses->clause('INTO', $tokens[3], $tokens[2], true), $clauses->clause('INTO', $tokens[4], $tokens[3], true), $clauses->clause(null, $tokens[2], $tokens[1], false)]);
    }
}
