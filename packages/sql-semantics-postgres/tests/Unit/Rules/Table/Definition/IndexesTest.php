<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Indexes::class)]
#[Medium]
final class IndexesTest extends TestCase
{
    public function testDeriveDerivesTheKeysAndThePredicate(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE INDEX ON t (zz) INCLUDE (yy) WHERE c', $context);
        self::assertSame([
          0 => 'Column zz does not exist.',
          1 => 'Column yy does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testLocatedPutsAnUnqualifiedNameInTheSchema(): void
    {
        self::assertSame('s', (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Indexes())->located(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s'))->schema?->value);
    }

    public function testWriteWritesTheIndex(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE INDEX i ON t USING gist (a) WITH (fillfactor = 70) TABLESPACE s WHERE a > 0', []);
        self::assertSame('CREATE INDEX i ON t USING gist (a) WITH (fillfactor = 70) TABLESPACE s WHERE a > 0', $statement->toString());
    }

    public function testDeriveReportsAView(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [$semantics->diagnose('CREATE VIEW v AS SELECT 1 AS a'), $semantics->diagnose('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a')];
        self::assertSame(['cannot create index on relation "v"'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('CREATE INDEX i ON v (a)', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('CREATE INDEX i ON m (a)', $context)->facts->diagnostics));
    }
}
