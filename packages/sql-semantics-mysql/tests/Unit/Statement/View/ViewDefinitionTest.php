<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ViewDefinition::class)]
#[Medium]
final class ViewDefinitionTest extends TestCase
{
    public function testRenderHeadWritesAlgorithmDefinerAndSecurity(): void
    {
        self::assertSame('CREATE ALGORITHM = TEMPTABLE DEFINER = u@`%` SQL SECURITY DEFINER VIEW v AS SELECT 1 AS a', (new Inspection())->inspect('CREATE ALGORITHM = TEMPTABLE DEFINER = \'u\'@\'%\' SQL SECURITY DEFINER VIEW v AS SELECT 1 AS a')->toString());
    }

    public function testRenderWritesNameColumnsQueryAndCheckOption(): void
    {
        self::assertSame('CREATE VIEW db.v (a, b) AS SELECT 1, 2 WITH LOCAL CHECK OPTION', (new Inspection())->inspect('CREATE VIEW db.v (a, b) AS SELECT 1, 2 WITH LOCAL CHECK OPTION')->toString());
    }
}
