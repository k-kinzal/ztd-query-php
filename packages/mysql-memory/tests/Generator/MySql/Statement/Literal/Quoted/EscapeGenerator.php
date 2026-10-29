<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal\Quoted;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Escape;
use Tests\Generator\Composition;

/**
 * @implements ErisGenerator<Escape>
 */
final class EscapeGenerator implements ErisGenerator
{
    /**
     * @use Composition<Escape>
     */
    use Composition;

    /**
     * @param non-empty-list<Escape>|null $choices Applicable escape interpretations
     */
    public function __construct(?array $choices = null)
    {
        $this->delegate = Generators::elements($choices ?? Escape::cases());
    }
}
