<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DefaultLowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(DefaultLowering::class)]
#[Small]
final class DefaultLoweringTest extends TestCase
{
    public function testLowerKeepsADelimitedTruthWordAsText(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE c (a DEFAULT "true")');
        self::assertInstanceOf(CreateTable::class, $operation->statement);
        $constraint = $operation->statement->columns[0]->constraints[0] ?? null;
        self::assertInstanceOf(DefaultWord::class, $constraint);
        $default = (new DefaultLowering())->lower($constraint, $operation->binding());

        self::assertInstanceOf(TextLiteral::class, $default);
        self::assertSame('true', $default->value);
    }
}
