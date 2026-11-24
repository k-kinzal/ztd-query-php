<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\AnalyzeLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(AnalyzeLowering::class)]
#[Small]
final class AnalyzeLoweringTest extends TestCase
{
    public function testLowerUsesTheSchemaBeforeALikeNamedTable(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'archive'));
        $definitions = $semantics->context(['CREATE TABLE main.archive (code TEXT)']);
        $result = $semantics->analyze('ANALYZE ARCHIVE', $definitions);

        self::assertInstanceOf(Analyze::class, $result);
        self::assertInstanceOf(Identifier::class, $result->target);
        self::assertSame('archive', $result->target->value);
        self::assertSame('ANALYZE "archive"', $result->toString());
    }

    public function testLowerUsesAnIndexBeforeATableInAnotherSchema(): void
    {
        $semantics = new Semantics();
        $definitions = $semantics->context([
            'CREATE TABLE temp.rows (n INTEGER)',
            'CREATE TABLE main.label (code TEXT)',
            'CREATE INDEX temp.label ON rows(n)',
        ]);
        $result = $semantics->analyze('ANALYZE label', $definitions);

        self::assertInstanceOf(Analyze::class, $result);
        self::assertInstanceOf(IndexTarget::class, $result->target);
        self::assertSame('rows', $result->target->table->value);
        self::assertSame('ANALYZE "temp"."label"', $result->toString());
    }
}
