<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(Invalid::class)]
#[Medium]
final class InvalidTest extends TestCase
{
    public function testCauseIsTheDiagnosticThatLeavesTheExpressionWithoutAType(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');

        $field = $semantics->analyze('SELECT b FROM t', [$table])->field('b');

        self::assertInstanceOf(Invalid::class, $field->type);
        self::assertInstanceOf(MissingColumn::class, $field->type->cause);
        self::assertSame($field->resolution, $field->type->cause);
        self::assertSame('Column b does not exist.', $field->type->cause->message());
    }
}
