<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Prepared;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Definition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition>
 */
final class DefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation> $initial Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $database Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative>> $alternatives Generated constructor operand
     */
    public function __construct(ErisGenerator $initial, ErisGenerator $database, ErisGenerator $alternatives)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition(initial: $parts[0], database: $parts[1], alternatives: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $initial,
            $database,
            $alternatives
        ));
    }

    /**
     * Composes a mode-independent statement across the interpretation domain.
     * Its varying statement operands remain upstream of every alternative.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative> $initials Initial interpretations of ASCII statements
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition>
     */
    public static function portable(ErisGenerator $initials): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative $initial): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition {
            $language = $initial->interpretation->language;
            $alternatives = [];
            foreach (\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation::all($language->grammar, $language->parameters) as $interpretation) {
                $alternatives[] = $interpretation->key() === $initial->interpretation->key() ? $initial : new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($interpretation, $initial->statement, $initial->sources);
            }
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition($initial->interpretation, new \SqlSemantics\Statement\Identifier\Name('pbt'), $alternatives);
        }, $initials);
    }

    /**
     * Composes a preparation whose escaped literal stops being grammatical when backslashes are disabled.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative> $initials Initial interpretations containing an escaped single quote
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition>
     */
    public static function changingEscapes(ErisGenerator $initials): ErisGenerator
    {
        $refusals = new SyntaxRefusalGenerator(Generators::choose(1, 128), Generators::choose(1, 4), Generators::elements(["quoted'", "value'", "text'"]));
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition {
            [$initial, $refusal] = $parts;
            $alternatives = [];
            foreach (\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation::all($initial->interpretation->language->grammar, $initial->interpretation->language->parameters) as $interpretation) {
                $alternatives[] = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($interpretation, $interpretation->language->lexical->noBackslashEscapes ? $refusal : $initial->statement, $initial->sources);
            }
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition($initial->interpretation, new \SqlSemantics\Statement\Identifier\Name('pbt'), $alternatives);
        }, \Tests\Generator\Operands::tuple($initials, $refusals));
    }

}
