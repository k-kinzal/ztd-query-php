<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use SqlSemantics\Contract\SearchPath as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * A variadic search path contains at least one nonempty schema name in precedence order.
 */
final class SearchPathGenerator implements Inhabitant
{
    /**
     * Generates a nonempty argument list while preserving schema precedence and duplicates.
     */
    public function sample(Choices $choices): Model
    {
        $schemas = [];
        for ($index = 0, $count = $choices->integer(1, 5); $index < $count; $index++) {
            $schemas[] = $choices->one(['d', 'app', 'archive', 'schema with spaces', '日本語']);
        }
        return new Model(...$schemas);
    }
}
