<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SourceOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption>
 */
final class SourceOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral|\SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds|\SqlSemantics\Platform\MySql\Statement\Name\AccountName|\SqlSemantics\Platform\MySql\Statement\Replication\Source\PrimaryKeyCheck|\SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids|null> $value Generated constructor operand
     * @param ErisGenerator<bool>|null $synonym Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ErisGenerator $kind, ErisGenerator $value, ?ErisGenerator $synonym = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption(terminology: $parts[0], kind: $parts[1], value: $parts[2], synonym: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $terminology,
            $kind,
            $value,
            $synonym ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
