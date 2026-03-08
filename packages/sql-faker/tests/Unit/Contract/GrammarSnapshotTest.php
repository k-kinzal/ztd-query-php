<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GrammarRuleSnapshot;
use SqlFaker\Contract\GrammarSnapshot;

#[CoversClass(GrammarSnapshot::class)]
final class GrammarSnapshotTest extends TestCase
{
    public function testConstructsReadonlyGrammarSnapshot(): void
    {
        $rule = new GrammarRuleSnapshot('stmt', []);
        $snapshot = new GrammarSnapshot(
            'mysql',
            'stmt',
            ['stmt'],
            ['stmt' => $rule],
            ['family.id' => ['stmt']],
        );

        self::assertSame('mysql', $snapshot->dialect);
        self::assertSame('stmt', $snapshot->startRule);
        self::assertSame(['stmt'], $snapshot->entryRules);
        self::assertSame($rule, $snapshot->rules['stmt']);
        self::assertSame(['stmt'], $snapshot->familyAnchors['family.id']);
    }
}
