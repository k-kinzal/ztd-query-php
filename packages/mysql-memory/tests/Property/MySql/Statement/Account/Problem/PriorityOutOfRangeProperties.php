<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange as Model;
use Tests\Property\SemanticValueProperty;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class PriorityOutOfRangeProperties extends TestCase
{
    use SemanticValueProperty;

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
