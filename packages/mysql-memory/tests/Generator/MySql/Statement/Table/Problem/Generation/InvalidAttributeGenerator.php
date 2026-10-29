<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Generation;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\InvalidAttribute;
use Tests\Generator\Composition;

/**
 * Composes the column and incompatible generated attribute through Eris.
 *
 * @implements ErisGenerator<InvalidAttribute>
 */
final class InvalidAttributeGenerator implements ErisGenerator
{
    /** @use Composition<InvalidAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column The generated column name
     * @param ErisGenerator<string> $attribute The incompatible attribute or type
     */
    public function __construct(ErisGenerator $column, ErisGenerator $attribute)
    {
        $this->delegate = Generators::map(static fn (array $parts): InvalidAttribute => new InvalidAttribute($parts[0], $parts[1]), Generators::tuple($column, $attribute));
    }

    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<InvalidAttribute>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\Generation\\InvalidAttribute' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $value): string => 'g' . $value, Generators::choose(0, 1000))), Generators::elements(['DEFAULT', 'ON UPDATE', 'AUTO_INCREMENT', 'SERIAL DEFAULT VALUE', 'COLUMN_FORMAT', 'STORAGE', 'SERIAL']))];
    }
}
