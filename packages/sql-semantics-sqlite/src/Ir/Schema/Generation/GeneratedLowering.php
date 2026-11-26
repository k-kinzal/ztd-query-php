<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Generation;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\GeneratedStorage;

/**
 * Resolves a generated expression against the declared column slots.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class GeneratedLowering
{
    /**
     * Records the explicit storage role and the checked scalar expression.
     */
    public function lower(Generated $parsed, SourceBinding $binding): ColumnGeneration
    {
        $storage = $parsed->storage();
        Check::input($storage !== null, 'A generated column requires a known storage mode.');
        $mode = $storage === GeneratedStorage::Virtual ? Storage::Virtual : Storage::Stored;
        $expression = (new ExpressionLowering())->lower($parsed->expression, $binding);
        Check::input(!$expression instanceof Parameter, 'A schema expression cannot contain a parameter.');

        return new ColumnGeneration($mode, $expression);
    }
}
