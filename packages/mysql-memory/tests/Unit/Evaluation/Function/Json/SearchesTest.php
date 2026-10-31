<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Json\Searches;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Searches::class)]
#[Small]
final class SearchesTest extends TestCase
{
    public function testRoutinesNamesTheFunctions(): void
    {
        self::assertSame(['JSON_EXTRACT', 'JSON_CONTAINS', 'JSON_CONTAINS_PATH', 'JSON_SEARCH', 'JSON_OVERLAPS'], array_map(static fn ($routine): string => $routine->name, (new Searches())->routines()));
    }

    public function testExtractAnswersWhatEveryPathSelects(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_EXTRACT('[1,2]', '$[0]', '$[7]'), JSON_EXTRACT('[1,2]', '$[*]', '$[0]'), JSON_EXTRACT('[1,2]', '$[7]', '$[8]'), JSON_EXTRACT('[1]', NULL, 'x'), JSON_EXTRACT(NULL, 'x')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['[1]', '[1, 2, 1]', null, null, null]], $result->rows);
    }

    public function testContainsFindsTheCandidateInTheTarget(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_CONTAINS('[1,2,[3,4]]', '[1,3]'), JSON_CONTAINS('{\"a\":{\"x\":1}}', '{\"x\":1}'), JSON_CONTAINS('{\"a\":[[1]]}', '1', '$.a'), JSON_CONTAINS('[1]', '1', '$[5]')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', null]], $result->rows);
    }

    public function testContainsPathStopsAtThePathThatDecides(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_CONTAINS_PATH('{\"a\":1}', 'one', '$.a', 'x'), JSON_CONTAINS_PATH('{\"a\":1}', 'ALL', '$.a', '$.b'), JSON_CONTAINS_PATH('{\"a\":1}', 'one', NULL, '$.a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', null]], $result->rows);
    }

    public function testOneOrAllRefusesAnyOtherWord(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([true, false, null], [Searches::oneOrAll(new Constant(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci')), 'ALL'), $frame, 'f'), Searches::oneOrAll(new Constant(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci')), 'one'), $frame, 'f'), Searches::oneOrAll(new Constant(Domain::null(), null), $frame, 'f')]);
        $this->expectExceptionMessage("The oneOrAll argument to json_search may take these values: 'one' or 'all'.");
        Searches::oneOrAll(new Constant(Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), ' one'), $frame, 'json_search');
    }

    public function testSearchAnswersThePathsOfTheMatches(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_SEARCH('[\"abc\",{\"x\":\"abc\",\"y\":[\"abd\"]}]', 'one', 'ab%'), JSON_SEARCH('[\"abc\",{\"x\":\"abc\",\"y\":[\"abd\"]}]', 'all', 'ab%'), JSON_SEARCH('[\"a_c\",\"abc\"]', 'all', 'a|_c', '|'), JSON_SEARCH('[\"ABC\"]', 'all', 'abc'), JSON_SEARCH('[\"ABC\"]', 'all', CAST('abc' AS CHAR))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['"$[0]"', '["$[0]", "$[1].x", "$[1].y[0]"]', '"$[0]"', null, '"$[0]"']], $result->rows);
    }

    public function testEscapingIgnoresAMultibyteEscapeInABinaryCollation(): void
    {
        self::assertSame(['', '|', 'é'], [Searches::escaping(['é', Charset::known('utf8mb4')], Collation::known('utf8mb4_bin')), Searches::escaping(['|', Charset::known('ascii')], Collation::known('utf8mb4_bin')), Searches::escaping(['é', Charset::known('utf8mb4')], Collation::known('utf8mb4_0900_ai_ci'))]);
    }

    public function testEscapeRefusesMoreThanOneCharacter(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['\\', ''], [Searches::escape(new Constant(Domain::null(), null), $frame)[0], Searches::escape(new Constant(Domain::string(0, Collation::known('utf8mb4_0900_ai_ci')), ''), $frame)[0]]);
        $this->expectExceptionMessage('Incorrect arguments to ESCAPE');
        Searches::escape(new Constant(Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')), 'ab'), $frame);
    }

    public function testTextReadsAStringInUtf8mb4(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['é', null, '12'], [Searches::text(new Constant(Domain::string(1, Collation::known('latin1_swedish_ci')), "\xE9"), $frame), Searches::text(new Constant(Domain::null(), null), $frame), Searches::text(new Constant(Domain::integer(), 12), $frame)]);
    }

    public function testOverlapsFindsACommonValue(): void
    {
        $result = (new Instance())->connect()->query("SELECT JSON_OVERLAPS('[1,2]', '[2,3]'), JSON_OVERLAPS('[[1]]', '[1]'), JSON_OVERLAPS('[1]', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', null]], $result->rows);
    }
}
