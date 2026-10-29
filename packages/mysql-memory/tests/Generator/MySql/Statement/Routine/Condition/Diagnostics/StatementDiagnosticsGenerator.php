<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StatementDiagnostics values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics>
 */
final class StatementDiagnosticsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics(items: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $items,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
