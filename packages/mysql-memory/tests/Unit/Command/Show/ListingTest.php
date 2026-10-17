<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Listing::class)]
#[Small]
final class ListingTest extends TestCase
{
    public function testResultFiltersByLikeWithoutRegardToCase(): void
    {
        $session = (new Instance())->connect();

        $result = $session->query("SHOW VARIABLES LIKE 'AUTOCOMMIT'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['autocommit', 'ON']], $result->rows);
    }

    public function testResultFiltersByWhereOverTheColumns(): void
    {
        $session = (new Instance())->connect();

        $result = $session->query("SHOW COLLATION WHERE Charset = 'latin1' AND `Default` = 'Yes'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['latin1_swedish_ci', 'latin1', '8', 'Yes', 'Yes', '1', 'PAD SPACE']], $result->rows);
    }

    public function testMixRefusesAPatternTheColumnCannotHold(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1267);
        $this->expectExceptionMessage("Illegal mix of collations (utf8mb3_tolower_ci,EXPLICIT) and (utf8mb4_0900_ai_ci,COERCIBLE) for operation 'like'");

        $session->query("SHOW COLUMNS FROM t LIKE '\u{1F600}'");
    }

    public function testMixAcceptsAPatternOfAColumnInTheCharacterSetOfTheConnection(): void
    {
        $session = (new Instance())->connect();
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);
        (new Listing([]))->mix("\u{1F600}", 'utf8mb4_0900_ai_ci', 'IMPLICIT', $context);
        $result = $session->query("SHOW VARIABLES LIKE '\u{1F600}'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testLikeMatchesTheNamedColumnInACollation(): void
    {
        $session = (new Instance())->connect();
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);
        $listing = new Listing([Heading::text('Name', Field::VarString, 64)]);

        self::assertSame([['Ab']], $listing->like([['Ab'], ['b']], 'a%', 0, 'utf8mb3_general_ci', $context));
        self::assertSame([], $listing->like([['Ab'], ['b']], 'a%', 0, 'utf8mb3_bin', $context));
    }

    public function testWhereEvaluatesAConditionFixedForTheStatementOnce(): void
    {
        $session = (new Instance())->connect();

        $result = $session->query("SHOW VARIABLES WHERE 'x' + 0 = 1")[0];

        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]], $warnings->rows);
    }

    public function testSentWritesEachValueInItsText(): void
    {
        $session = (new Instance())->connect();
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);

        $result = (new Listing([Heading::text('Name', Field::VarString, 64), new Heading('Id', Field::LongLong, 20)]))->sent([['a', 7], ['b', null]], $context);

        self::assertSame([['a', '7'], ['b', null]], $result->rows);
        self::assertSame(['Name', 'Id'], [$result->columns[0]->name, $result->columns[1]->name]);
    }
}
