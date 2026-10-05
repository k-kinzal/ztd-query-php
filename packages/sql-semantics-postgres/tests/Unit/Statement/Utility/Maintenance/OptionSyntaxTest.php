<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionSyntax;

#[CoversClass(OptionSyntax::class)]
#[Medium]
final class OptionSyntaxTest extends TestCase
{
    public function testCasesTellTheTwoSyntaxesApart(): void
    {
        $words = (new Semantics(Dialect::PostgreSql))->analyze('CLUSTER VERBOSE t')->statement;
        $list = (new Semantics(Dialect::PostgreSql))->analyze('CLUSTER (VERBOSE) t')->statement;
        self::assertInstanceOf(Cluster::class, $words);
        self::assertInstanceOf(Cluster::class, $list);
        self::assertSame([OptionSyntax::Words, OptionSyntax::Parenthesized], [$words->syntax, $list->syntax]);
    }
}
