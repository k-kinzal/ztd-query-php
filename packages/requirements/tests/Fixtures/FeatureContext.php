<?php

declare(strict_types=1);

namespace Requirements\Tests\Fixtures;

use Behat\Behat\Context\Context;
use RuntimeException;

final class FeatureContext implements Context
{
    /** @Given a passing step */
    public function passing(): void
    {
    }

    /** @Given a failing step */
    public function failing(): void
    {
        throw new RuntimeException('Expected failure.');
    }
}
