<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

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

    #[\Override]
    protected function nativeIntegerType(): string
    {
        return 'INTEGER';
    }

    #[\Override]
    protected function nativeStringType(): string
    {
        return 'TEXT';
    }
}
