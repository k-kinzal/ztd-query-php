<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionSyntax;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(OptionSyntax::class)]
#[Medium]
final class OptionSyntaxTest extends TestCase
{
    public function testCasesTellTheTwoSyntaxesApart(): void
    {
        $words = (new Inspection())->inspect('CLUSTER VERBOSE t')->statement;
        $list = (new Inspection())->inspect('CLUSTER (VERBOSE) t')->statement;
        self::assertInstanceOf(Cluster::class, $words);
        self::assertInstanceOf(Cluster::class, $list);
        self::assertSame([OptionSyntax::Words, OptionSyntax::Parenthesized], [$words->syntax, $list->syntax]);
    }
}
