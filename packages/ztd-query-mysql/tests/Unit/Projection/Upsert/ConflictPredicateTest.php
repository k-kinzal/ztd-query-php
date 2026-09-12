<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\Upsert\ConflictPredicate;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[CoversClass(ConflictPredicate::class)]
final class ConflictPredicateTest extends TestCase
{
    public function testConflictPredicate(): void
    {
        $predicate = new ConflictPredicate(new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter());
        self::assertSame('((old.`id` = incoming.`id` AND old.`code` = incoming.`code`) OR (old.`email` = incoming.`email`))', $predicate->conflictPredicate(['PRIMARY' => ['id', 'code'], 'uq' => ['email'], 'empty' => []], 'old', 'incoming'));
        self::assertSame('FALSE', $predicate->conflictPredicate(['empty' => []], 'old', 'incoming'));
    }

}
