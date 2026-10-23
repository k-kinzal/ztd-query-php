<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings as Model;
use Tests\Property\ExecutableValueProperty;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class ShowWarningsProperties extends TestCase
{
    use ExecutableValueProperty;

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
