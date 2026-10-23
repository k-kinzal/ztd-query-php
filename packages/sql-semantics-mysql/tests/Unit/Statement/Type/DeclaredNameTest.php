<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;

#[CoversNothing]
#[Small]
final class DeclaredNameTest extends TestCase
{
    public function testDeclarationsExcludeCastTargets(): void
    {
        self::assertContains(DeclaredName::class, class_implements(Integral::class));
        self::assertNotContains(DeclaredName::class, class_implements(CastTarget::class));
    }
}
