<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine\Trigger;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent as Model;
use Tests\Property\SemanticValueProperty;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class TriggerEventProperties extends TestCase
{
    use SemanticValueProperty;

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
