<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ParseGeneratedColumn::class)]
#[Medium]
final class ParseGeneratedColumnTest extends TestCase
{
    public function testDeriveStatementSeesNoTable(): void
    {
        $statement = (new Inspection('5.7.44'))->diagnose('PARSE_GCOL_EXPR(a + 1)');

        self::assertInstanceOf(ParseGeneratedColumn::class, $statement->statement);
        self::assertSame('Column a does not exist.', $statement->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('PARSE_GCOL_EXPR(1)', (new Inspection('5.7.44'))->diagnose('PARSE_GCOL_EXPR (1)')->toString());
    }
}
