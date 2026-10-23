<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection as Model;
use Tests\Property\SemanticValueProperty;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class IndexDirectionProperties extends TestCase
{
    use SemanticValueProperty;

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
