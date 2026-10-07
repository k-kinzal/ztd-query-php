<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Pattern;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Pattern::class)]
#[Small]
final class PatternTest extends TestCase
{
    public function testCharactersSplitsUtf8TextIntoCharacters(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $pattern = new Pattern(new Constant(Domain::string(1, $collation), 'a'), new Constant(Domain::string(1, $collation), 'a'), null, $collation, false, Domain::integer());

        self::assertSame([['a', 'é', 'b'], []], [$pattern->characters('aéb'), $pattern->characters('')]);
    }

    public function testCharactersSplitsTextIntoBytesForABinaryCollation(): void
    {
        $collation = Collation::binary();
        $pattern = new Pattern(new Constant(Domain::string(1, $collation), 'a'), new Constant(Domain::string(1, $collation), 'a'), null, $collation, false, Domain::integer());

        self::assertSame(["\xC3", "\xA9"], $pattern->characters('é'));
    }

    public function testTokensReadsWildcardsAndEscapedCharacters(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $pattern = new Pattern(new Constant(Domain::string(1, $collation), 'a'), new Constant(Domain::string(1, $collation), 'a'), null, $collation, false, Domain::integer());

        self::assertSame(
            [['c', 'a'], ['%', ''], ['_', ''], ['c', '%'], ['c', '\\']],
            $pattern->tokens(['a', '%', '_', '\\', '%', '\\'], '\\'),
        );
    }

    public function testTokensReadsEveryCharacterLiterallyWithoutAnEscape(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $pattern = new Pattern(new Constant(Domain::string(1, $collation), 'a'), new Constant(Domain::string(1, $collation), 'a'), null, $collation, false, Domain::integer());

        self::assertSame([['c', '\\'], ['_', '']], $pattern->tokens(['\\', '_'], ''));
    }

    public function testMatchMatchesCharactersInTheCollation(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $pattern = new Pattern(new Constant(Domain::string(1, $collation), 'a'), new Constant(Domain::string(1, $collation), 'a'), null, $collation, false, Domain::integer());

        self::assertSame(
            [true, true, false, true, false],
            [
                $pattern->match(['A', 'b', 'c'], 0, [['c', 'a'], ['%', '']], 0),
                $pattern->match(['x', 'a'], 1, [['c', 'A']], 0),
                $pattern->match(['a', 'a'], 0, [['%', ''], ['c', 'a'], ['%', ''], ['c', 'a'], ['%', ''], ['c', 'a'], ['%', '']], 0),
                $pattern->match(['a', 'a', 'a'], 0, [['%', ''], ['c', 'a'], ['%', ''], ['c', 'a'], ['%', ''], ['c', 'a'], ['%', '']], 0),
                $pattern->match(['a', 'b'], 0, [['_', '']], 0),
            ],
        );
    }

    public function testEvaluateMatchesAConstantSubjectAgainstAPattern(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $like = new Pattern(new Constant(Domain::string(3, $collation), 'abc'), new Constant(Domain::string(2, $collation), 'A%'), null, $collation, false, Domain::integer());
        $notLike = new Pattern(new Constant(Domain::string(3, $collation), 'abc'), new Constant(Domain::string(2, $collation), 'A%'), null, $collation, true, Domain::integer());
        $null = new Pattern(new Constant(Domain::null(), null), new Constant(Domain::string(2, $collation), 'A%'), null, $collation, false, Domain::integer());

        self::assertSame([1, 0, null], [$like->evaluate($frame), $notLike->evaluate($frame), $null->evaluate($frame)]);
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $domain = Domain::integer();
        $pattern = new Pattern(new Constant(Domain::string(1, $collation), 'a'), new Constant(Domain::string(1, $collation), 'a'), null, $collation, false, $domain);

        self::assertSame($domain, $pattern->domain());
    }

    public function testEvaluateMatchesWildcards(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'abc' LIKE 'a%', 'abc' LIKE 'a_', 'abc' LIKE '_b_', 'abc' LIKE 'a%%c', '' LIKE '%', 'abc' NOT LIKE 'b%', 'a' LIKE 'a '")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '1', '1', '1', '0']], $result->rows);
    }

    public function testEvaluateMatchesInTheCollationOfTheOperation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'ABC' LIKE 'a%', 'é' LIKE 'e', 'é' LIKE '_', 'abc' COLLATE utf8mb4_bin LIKE 'A%', _binary'é' LIKE '_', _binary'é' LIKE '__'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '0', '0', '1']], $result->rows);
    }

    public function testEvaluateMakesTheCharacterAfterABackslashLiteral(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a%c' LIKE 'a\\%c', 'abc' LIKE 'a\\%c', 'a_c' LIKE 'a\\_c', 'abc' LIKE 'a\\_c', 'a' LIKE 'a\\\\'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '0', '0']], $result->rows);
    }

    public function testEvaluateUsesTheEscapeCharacterOfTheEscapeClause(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a_c' LIKE 'a|_c' ESCAPE '|', 'abc' LIKE 'a|_c' ESCAPE '|', 'a%' LIKE 'a%%' ESCAPE '%', 'a\\\\b' LIKE 'a\\\\b' ESCAPE '', 'a\\\\b' LIKE 'a\\\\b'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '1', '0']], $result->rows);
    }

    public function testEvaluateAnswersNullForANullOperand(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT NULL LIKE 'a', 'a' LIKE NULL, NULL NOT LIKE 'a', 123 LIKE '1%'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null, '1']], $result->rows);
    }

    public function testEvaluateMatchesTheColumnOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(10))');
        $session->query("INSERT INTO t VALUES (1, 'Apple'), (2, 'banana'), (3, NULL)");
        $result = $session->query("SELECT id, s LIKE 'a%', s LIKE '%an%' FROM t ORDER BY id")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0'], ['2', '0', '1'], ['3', null, null]], $result->rows);
    }
}
