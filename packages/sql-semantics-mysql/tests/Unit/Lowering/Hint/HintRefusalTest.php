<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Hint\HintRefusal;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure;

#[CoversClass(HintRefusal::class)]
#[Small]
final class HintRefusalTest extends TestCase
{
    public function testRefusalCarriesTheErrorAndItsText(): void
    {
        $error = new HintError(HintFailure::Size, 3);
        $refusal = new HintRefusal($error);

        self::assertSame([$error, 'A size parameter was incorrectly specified, either number or on the form 10M'], [$refusal->error, $refusal->getMessage()]);
    }
}
