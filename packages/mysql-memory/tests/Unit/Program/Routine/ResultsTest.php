<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Routine;

use MySqlMemory\Program\Routine\Results;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;

#[CoversClass(Results::class)]
#[Small]
final class ResultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerBodies(): iterable
    {
        yield 'profile' => ['SHOW PROFILE', false];
        yield 'profiles' => ['SHOW PROFILES', false];
        yield 'ordinary select' => ['SELECT 1', true];
        yield 'unreachable select' => ['BEGIN IF FALSE THEN SELECT 1; END IF; SHOW PROFILE; END', true];
        yield 'unreachable warnings' => ['BEGIN IF FALSE THEN SHOW WARNINGS; END IF; SHOW PROFILE; END', true];
        yield 'nested call' => ['CALL q()', false];
        yield 'dynamic execution' => ['EXECUTE s', true];
        yield 'select into' => ['BEGIN SELECT 1 INTO @v; SHOW PROFILE; END', false];
        yield 'cursor' => ['BEGIN DECLARE c CURSOR FOR SELECT 1; SHOW PROFILE; END', false];
    }

    #[DataProvider('providerBodies')]
    public function testAnnouncedReadsDeclaredInstructionsInsteadOfTheirReachability(string $body, bool $expected): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ' . $body)->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);

        self::assertSame($expected, (new Results())->announced($statement->body));
    }
}
