<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\TriviaRule;

#[CoversClass(TriviaRule::class)]
#[Small]
final class TriviaRuleTest extends TestCase
{
    public function testIndexRecordsTheTriviaBeforeTheNextTokenOfTheCommand(): void
    {
        $tree = (new SqliteParser())->parse('SELECT 1+1 /* c */ , 2 -- d');
        $expressions = $tree->find('expr');
        $trivia = new TriviaRule();
        $trivia->index($tree->find('ecmd')[0]);

        self::assertSame(' /* c */ ', $trivia->after($expressions[0]));
        self::assertSame(' -- d', $trivia->after($expressions[3]));
    }

    public function testIndexForgetsThePreviousCommand(): void
    {
        $tree = (new SqliteParser())->parse('SELECT 1 /* c */; SELECT 2');
        $commands = $tree->find('ecmd');
        $trivia = new TriviaRule();
        $trivia->index($commands[0]);
        $trivia->index($commands[1]);
        $this->expectExceptionMessage('The trivia after a region is read from an indexed command.');

        $trivia->after($commands[0]->find('expr')[0]);
    }

    public function testAfterRefusesARegionOfACommandThatWasNotIndexed(): void
    {
        $tree = (new SqliteParser())->parse('SELECT 1');
        $this->expectExceptionMessage('The trivia after a region is read from an indexed command.');

        (new TriviaRule())->after($tree->find('expr')[0]);
    }

    public function testAfterRefusesAnEmptyRegion(): void
    {
        $this->expectExceptionMessage('A region followed by trivia covers at least one token.');

        (new TriviaRule())->after(new Node('as', 2, []));
    }
}
