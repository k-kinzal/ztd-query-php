<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Policy\RelationRules;

#[CoversClass(RelationRules::class)]
#[Small]
final class RelationRulesTest extends TestCase
{
    public function testMatchingSelectsSitesByRuleAndRequiredSymbols(): void
    {
        $rules = new RelationRules(['table_ident'], drops: [
            ['rule' => 'drop', 'requires' => ['table_or_tables'], 'names' => 'table_list'],
            ['rule' => 'drop', 'requires' => ['VIEW_SYM'], 'names' => 'table_list'],
        ]);
        self::assertSame(['table_ident'], $rules->nameSymbols);
        self::assertCount(1, RelationRules::matching($rules->drops, 'drop', ['DROP', 'table_or_tables', 'table_list']));
        self::assertSame([], RelationRules::matching($rules->drops, 'drop', ['DROP', 'table_list']));
        self::assertSame([], RelationRules::matching($rules->drops, 'other', ['DROP', 'table_or_tables', 'table_list']));
        self::assertSame([], $rules->declarations);
    }
}
