<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;

#[CoversClass(HintForm::class)]
#[Small]
final class HintFormTest extends TestCase
{
    public function testCasesNameTheForms(): void
    {
        self::assertSame(['Table', 'JoinOrder', 'FixedOrder', 'Key', 'Semijoin', 'Subquery', 'ExecutionTime', 'ResourceGroup', 'Variable', 'BlockName'], array_column(HintForm::cases(), 'name'));
    }
}
