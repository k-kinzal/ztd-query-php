<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(Invalid::class)]
#[Medium]
final class InvalidTest extends TestCase
{
    public function testCauseIsTheDiagnosticThatLeavesTheExpressionWithoutAType(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');

        $field = $semantics->inspect('SELECT b FROM t', [$table])->field('b');

        self::assertInstanceOf(Invalid::class, $field->type);
        self::assertInstanceOf(MissingColumn::class, $field->type->cause);
        self::assertSame($field->resolution, $field->type->cause);
        self::assertSame('Column b does not exist.', $field->type->cause->message());
    }
}
