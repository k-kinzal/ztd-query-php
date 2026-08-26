<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Contract\TransformerContractTest;
use Tests\Fake\FakeSqlTransformer;
use ZtdQuery\Rewrite\SqlTransformer;

#[CoversNothing]
final class SqlTransformerTest extends TransformerContractTest
{
    protected function createTransformer(): SqlTransformer
    {
        return new FakeSqlTransformer();
    }

    protected function selectSql(): string
    {
        return 'SELECT * FROM users WHERE id = 1';
    }

    public function testViewContextIsRenderedAsCte(): void
    {
        $result = $this->createTransformer()->transform(
            'SELECT * FROM active_users',
            ['active_users' => ['viewSql' => 'SELECT * FROM users WHERE active = 1']],
        );

        self::assertSame(
            'WITH "active_users" AS (SELECT * FROM users WHERE active = 1) SELECT * FROM active_users',
            $result,
        );
    }

    #[Override]
    protected function nativeIntegerType(): string
    {
        return 'INTEGER';
    }

    #[Override]
    protected function nativeStringType(): string
    {
        return 'TEXT';
    }
}
