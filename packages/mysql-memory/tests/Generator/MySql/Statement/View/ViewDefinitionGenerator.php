<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ViewDefinition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\ViewDefinition>
 */
final class ViewDefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\ViewDefinition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query> $query Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>|null>|null $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm|null>|null $algorithm Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\ViewSecurity|null>|null $security Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption|null>|null $check Generated constructor operand
     * @param ErisGenerator<bool>|null $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $query, ?ErisGenerator $columns = null, ?ErisGenerator $algorithm = null, ?ErisGenerator $definer = null, ?ErisGenerator $security = null, ?ErisGenerator $check = null, ?ErisGenerator $ifNotExists = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition {
            return new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition(name: $parts[0], query: $parts[1], columns: $parts[2], algorithm: $parts[3], definer: $parts[4], security: $parts[5], check: $parts[6], ifNotExists: $parts[7], inputSource: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $query,
            $columns ?? Generators::constant(null),
            $algorithm ?? Generators::constant(null),
            $definer ?? Generators::constant(null),
            $security ?? Generators::constant(null),
            $check ?? Generators::constant(null),
            $ifNotExists ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
