<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OptimizerHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>
 */
final class OptimizerHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = Generators::oneOf(...$alternatives);
    }
    /**
     * Generates the two name-only hint forms with their distinct meanings.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>
     */
    public static function namedForms(): ErisGenerator
    {
        return Generators::bind(HintFormGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Hint\HintForm::ResourceGroup, \SqlSemantics\Platform\MySql\Statement\Hint\HintForm::BlockName]), static function (\SqlSemantics\Platform\MySql\Statement\Hint\HintForm $form): ErisGenerator {
            $names = Generators::map(static fn (int $suffix): string => 'generated_' . $suffix, Generators::choose(0, 32));
            return $form === \SqlSemantics\Platform\MySql\Statement\Hint\HintForm::ResourceGroup ? new Form\ResourceGroupHintGenerator($names) : new Form\BlockNameHintGenerator($names);
        });
    }

}
