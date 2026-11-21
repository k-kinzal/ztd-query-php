<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Command\RewriteRules::class)]
#[Medium]
final class RewriteRulesTest extends TestCase
{
    public function testDeriveReachesOldAndNewWithAQualifierInTheActions(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [];
        array_push($context, ...$semantics->inspect('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->inspect('CREATE RULE r AS ON INSERT TO t DO ALSO SELECT new.a, b', $context);
        self::assertSame([
          0 => 'Column b does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testWriteWritesTheRule(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->inspect('CREATE RULE r AS ON SELECT TO t DO INSTEAD SELECT 1', []);
        self::assertSame('CREATE RULE r AS ON SELECT TO t DO INSTEAD SELECT 1', $statement->toString());
    }

    public function testDeriveReportsARelationThatCannotHaveRules(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [$semantics->inspect('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a'), $semantics->inspect('CREATE FOREIGN TABLE f (a int) SERVER x')];
        self::assertSame(['rules on materialized views are not supported'], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CREATE RULE r AS ON INSERT TO m DO INSTEAD NOTHING', $context)->facts->diagnostics));
        self::assertSame(['relation "f" cannot have rules'], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CREATE RULE r AS ON INSERT TO f DO INSTEAD NOTHING', $context)->facts->diagnostics));
    }
}
