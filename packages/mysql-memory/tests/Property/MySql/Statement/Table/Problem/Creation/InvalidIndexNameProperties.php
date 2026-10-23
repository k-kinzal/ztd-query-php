<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\InvalidIndexName as Model;
use Tests\Property\SemanticValueProperty;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class InvalidIndexNameProperties extends TestCase
{
    use SemanticValueProperty;

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
