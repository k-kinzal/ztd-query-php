<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefaultNotConstant;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(DefaultNotConstant::class)]
#[Medium]
final class DefaultNotConstantTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame('The default value of column price is not constant.', (new DefaultNotConstant(new Name('price')))->message());
    }

    public function testMessageIsReportedForEachDefaultNotConstantOfADefinition(): void
    {
        $semantics = new Inspection();
        $operation = $semantics->diagnose('CREATE TABLE t (a DEFAULT ((SELECT 1)), b DEFAULT ("word"), c DEFAULT (random() + 1), d DEFAULT (CASE WHEN 1 THEN 2 END))', []);
        $columns = array_map(static fn (object $diagnostic): ?string => $diagnostic instanceof DefaultNotConstant ? $diagnostic->column->value : null, $operation->facts->diagnostics);

        self::assertSame(['a', 'b'], $columns);
    }
}
