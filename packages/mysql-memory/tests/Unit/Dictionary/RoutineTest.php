<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Routine::class)]
#[Small]
final class RoutineTest extends TestCase
{
    public function testKindAnswersProcedureOrFunction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() SELECT 1');
        $session->query('CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        self::assertSame(['PROCEDURE', 'FUNCTION'], [$schema->procedures['p']->kind(), $schema->functions['f']->kind()]);
    }

    public function testCreateWritesTheCharacteristicsThatDifferFromTheDefaults(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE DEFINER = 'bob'@'host' PROCEDURE p1 ( a  INT /* c */ ) LANGUAGE SQL NOT DETERMINISTIC READS SQL DATA SQL SECURITY INVOKER COMMENT 'it''s' select   1   /* x */  ");

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame("CREATE DEFINER=`bob`@`host` PROCEDURE `p1`( a  INT /* c */ )\n    READS SQL DATA\n    SQL SECURITY INVOKER\n    COMMENT 'it''s'\nselect   1   /* x */", $schema->procedures['p1']->create());
    }

    public function testLiteralEscapesQuotesBackslashesAndControlCharacters(): void
    {
        self::assertSame("'a\\\\b''c\\n'", Routine::literal("a\\b'c\n"));
    }

    public function testQuotedDoublesTheBackticks(): void
    {
        self::assertSame('`a``b`', Routine::quoted('a`b'));
    }

    public function testReturnedTypesAStringInTheCollationOfItsDatabaseWithoutDecimals(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d CHARACTER SET latin1');
        $session->query("CREATE FUNCTION d.f() RETURNS VARCHAR(3) DETERMINISTIC RETURN 'a'");
        $routine = $session->instance->dictionary->schema('d')?->functions['f'];
        self::assertNotNull($routine);

        $returned = $routine->returned();

        self::assertSame(['latin1_swedish_ci', 3, 0, true], [$returned->collation->name, $returned->length, $returned->decimals, $returned->nullable]);
    }
}
