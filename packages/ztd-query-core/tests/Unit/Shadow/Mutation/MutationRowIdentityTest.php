<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;

#[CoversClass(MutationRowIdentity::class)]
final class MutationRowIdentityTest extends TestCase
{
    public function testSeparatesOriginalCompositeKeyFromTheUpdatedRow(): void
    {
        self::assertSame(
            [
                'row' => ['tenant_id' => 2, 'id' => 20, 'value' => 'changed'],
                'identity' => ['tenant_id' => 1, 'id' => 10],
            ],
            (new MutationRowIdentity())->extract([
                'tenant_id' => 2,
                'id' => 20,
                'value' => 'changed',
                '__ztd_original_tenant_id' => 1,
                '__ztd_original_id' => 10,
            ], ['tenant_id', 'id']),
        );
    }

    public function testFallsBackToCurrentKeyForLegacyProjections(): void
    {
        self::assertSame(
            ['row' => ['id' => 1], 'identity' => ['id' => 1]],
            (new MutationRowIdentity())->extract(['id' => 1], ['id']),
        );
    }
}
