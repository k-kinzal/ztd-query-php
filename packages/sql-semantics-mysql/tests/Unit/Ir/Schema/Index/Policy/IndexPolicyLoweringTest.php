<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

#[CoversClass(IndexPolicyLowering::class)]
#[Small]
final class IndexPolicyLoweringTest extends TestCase
{
    public function testLowerKeepsNativeEffectiveOptionsInOppositeWrittenOrder(): void
    {
        $policy = (new IndexPolicyLowering())->lower([new LockOption(new Name('shARED')), new AlgorithmOption(new Name('cOpy'))]);

        self::assertSame(IndexAlgorithm::Copy, $policy->algorithm);
        self::assertSame(IndexLock::Shared, $policy->lock);
    }
}
