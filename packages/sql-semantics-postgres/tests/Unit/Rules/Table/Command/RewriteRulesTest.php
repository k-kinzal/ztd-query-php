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
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE RULE r AS ON INSERT TO t DO ALSO SELECT new.a, b', $context);
        self::assertSame([
          0 => 'Column b does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testWriteWritesTheRule(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE RULE r AS ON SELECT TO t DO INSTEAD SELECT 1', []);
        self::assertSame('CREATE RULE r AS ON SELECT TO t DO INSTEAD SELECT 1', $statement->toString());
    }
}
