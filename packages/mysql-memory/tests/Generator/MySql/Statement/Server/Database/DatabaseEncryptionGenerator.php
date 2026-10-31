<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;

/**
 * Generates DatabaseEncryption values from the generators of its operands.
 *
 * @implements ErisGenerator<DatabaseEncryption>
 */
final class DatabaseEncryptionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DatabaseEncryption> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DatabaseEncryption from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DatabaseEncryption => $parts[0] instanceof Encryption
                ? new DatabaseEncryption(encryption: $parts[0], spelling: $parts[1] ? new Text(strtolower($parts[0]->value)) : null, inputSource: null, operator: $parts[2], defaultKeyword: $parts[3])
                : new DatabaseEncryption(encryption: $parts[0], spelling: null, inputSource: null, operator: $parts[2], defaultKeyword: $parts[3]),
            Generators::tuple(
                Generators::bind(Generators::bool(), static fn (bool $setting): ErisGenerator => $setting ? new EncryptionGenerator($release) : Generators::suchThat(static fn (Text $text): bool => Encryption::fromOperand($text->value) === null, new TextGenerator($release))),
                Generators::bool(),
                Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release)),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DatabaseEncryption $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<DatabaseEncryption>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DatabaseEncryption>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
