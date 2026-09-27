<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Assert;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

/**
 * States that a composed value is what analyzing its own SQL gives back.
 */
final class Composed
{
    /**
     * A composed expression, embedded in a SELECT, is found again as an equal value with the same spelling.
     */
    public static function assertExpressionRoundTrips(Semantics $semantics, Element $expression): void
    {
        $statement = $semantics->analyze('SELECT ' . Writer::render($expression));
        $found = Traversal::find($statement->command, $expression::class);
        Assert::assertNotEmpty($found);
        Assert::assertSame(Writer::render($expression), Writer::render($found[0]));
        Assert::assertEquals($expression, $found[0]);
    }

    /**
     * A composed query is a command whose SQL analyzes back to the same SQL.
     */
    public static function assertQueryRoundTrips(Semantics $semantics, Element $query): void
    {
        $sql = Writer::render($query);
        Assert::assertSame($sql, $semantics->analyze($sql)->toString());
        Assert::assertInstanceOf(Command::class, $query);
        Assert::assertSame($sql, (new Statement($query))->toString());
    }
}
