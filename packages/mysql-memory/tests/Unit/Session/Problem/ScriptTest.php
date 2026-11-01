<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Script;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Script::class)]
#[Small]
final class ScriptTest extends TestCase
{
    public function testStatementsAnswersTheStatementsBeforeTheFirstThatDoesNotParseThenTheRest(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['SELECT 1;', ' SELECT 2;', ' SELECT FROM; SELECT 3'], (new Script())->statements($session->semantics(), 'SELECT 1; SELECT 2; SELECT FROM; SELECT 3'));
    }

    public function testStatementsAnswersNothingWhenTheFirstStatementDoesNotParse(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([], (new Script())->statements($session->semantics(), 'SELECT FROM; SELECT 1'));
    }

    public function testStatementsSkipsASemicolonInsideAString(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['SELECT 1;', " SELECT ';' FROM"], (new Script())->statements($session->semantics(), "SELECT 1; SELECT ';' FROM"));
    }
}
