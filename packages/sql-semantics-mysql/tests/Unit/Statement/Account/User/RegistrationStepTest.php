<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep;

#[CoversClass(RegistrationStep::class)]
#[Small]
final class RegistrationStepTest extends TestCase
{
    public function testCasesNameTheSteps(): void
    {
        self::assertSame(['Initiate', 'Unregister', 'Finish'], array_column(RegistrationStep::cases(), 'name'));
    }
}
