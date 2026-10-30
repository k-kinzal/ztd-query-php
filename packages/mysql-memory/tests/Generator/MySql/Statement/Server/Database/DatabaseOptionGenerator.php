<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DatabaseOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>
 */
final class DatabaseOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = Generators::oneOf(...$alternatives);
    }
    /**
     * Varies the option count, order, charset conflicts and release-specific settings.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>>
     */
    public static function sequence(\SqlSemantics\Contract\GrammarRelease $release, bool $alter): ErisGenerator
    {
        $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $charsets = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::elements(['latin1', 'utf8', 'utf8mb4', 'binary', 'missing_charset']));
        $collations = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::elements(['latin1_bin', 'utf8mb4_bin', 'binary', 'missing_collation']));
        $choices = [
            new DatabaseCharsetGenerator(new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator($legacy ? Generators::oneOf(Generators::constant(null), $charsets) : $charsets)),
            new DatabaseCollationGenerator(new \Tests\Generator\MySql\Statement\Name\CollationNameGenerator($legacy ? Generators::oneOf(Generators::constant(null), $collations) : $collations)),
        ];
        if (!$legacy) {
            $choices[] = new DatabaseEncryptionGenerator(Generators::oneOf(new EncryptionGenerator(), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::elements(['invalid', '', 'yes']))));
            if ($alter) {
                $choices[] = new DatabaseReadOnlyGenerator(Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::elements(['0', '1', '0001', '0.5', '1e2']))));
            }
        }
        $option = new self($choices);
        return Generators::bind(Generators::choose($alter ? 1 : 0, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $option));
    }

}
