<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Rewrite\Upsert\ConflictPredicate::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\Upsert\ExpressionBinder::class)]
final class ConflictPredicateTest extends TestCase
{
    public function testConflictPredicate(): void
    {
        self::assertSame('(("existing"."id" = "incoming"."id") OR ("existing"."email" = "incoming"."email"))', (new \ZtdQuery\Platform\Postgres\Rewrite\Upsert\ConflictPredicate(new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter()))->conflictPredicate(['pk' => ['id'], 'email_unique' => ['email']], '"existing"', '"incoming"'));
    }

    public function testQualified(): void
    {
        self::assertSame('"alias"."odd""name"', (new \ZtdQuery\Platform\Postgres\Rewrite\Upsert\ConflictPredicate(new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter()))->qualified('alias', 'odd"name'));
    }
    public function testWithPredicateConstrainsExistingAndIncomingRows(): void
    {
        $predicate = new \ZtdQuery\Platform\Postgres\Rewrite\Upsert\ConflictPredicate(new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter());
        self::assertSame('(base AND ("__ztd_existing"."active") AND ("__ztd_incoming"."active"))', $predicate->withPredicate('base', 'active', 'users', ['active'], ['EXCLUDED']));
    }
}
