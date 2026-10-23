<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery as Model;
use Tests\Property\ExecutableValueProperty;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class InQueryProperties extends TestCase
{
    use ExecutableValueProperty;

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
