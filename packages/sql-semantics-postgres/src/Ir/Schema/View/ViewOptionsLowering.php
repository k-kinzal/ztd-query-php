<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CheckOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView as ParsedCreateView;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * Resolves the three admitted native view options.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ViewOptionsLowering
{
    /**
     * Reads exact option presence and the native write check mode.
     * @return array{?bool, ?bool, ViewCheckOption}
     */
    public function lower(ParsedCreateView $statement): array
    {
        $options = [];
        foreach ($statement->options as $option) {
            $key = $option->name->value;
            Check::input($option->qualifier === null
                && in_array($key, ['security_barrier', 'security_invoker', 'check_option'], true)
                && !array_key_exists($key, $options), 'CREATE VIEW requires a distinct supported native option.');
            $options[$key] = $this->word($option->argument);
        }
        Check::input(
            $statement->checkOption === null || !array_key_exists('check_option', $options),
            'CREATE VIEW cannot repeat the check option.'
        );
        $check = match ($statement->checkOption) {
            CheckOption::Local => ViewCheckOption::Local,
            CheckOption::Cascaded, CheckOption::Unqualified => ViewCheckOption::Cascaded,
            null => $this->check($options),
        };

        return [$this->flag($options, 'security_barrier'), $this->flag($options, 'security_invoker'), $check];
    }

    /**
     * Resolves one checked Boolean spelling with its presence unchanged.
     * @param array<string, string|null> $options The known option words
     * @throws InvalidConstruction When the native option value is unknown
     */
    public function flag(array $options, string $key): ?bool
    {
        if (!array_key_exists($key, $options)) {
            return null;
        }

        return match ($options[$key]) {
            null, 'true', 'on', 'yes', '1' => true,
            'false', 'off', 'no', '0' => false,
            default => throw new InvalidConstruction('A PostgreSQL view security option needs a Boolean value.'),
        };
    }

    /**
     * Resolves the named form of the write-policy option.
     * @param array<string, string|null> $options The known option words
     * @throws InvalidConstruction When the native option value is unknown
     */
    public function check(array $options): ViewCheckOption
    {
        if (!array_key_exists('check_option', $options)) {
            return ViewCheckOption::None;
        }

        return match ($options['check_option']) {
            'local' => ViewCheckOption::Local,
            'cascaded' => ViewCheckOption::Cascaded,
            default => throw new InvalidConstruction('A PostgreSQL view check option must be local or cascaded.'),
        };
    }

    /**
     * Reads an option's decoded word before native option interpretation.
     * @throws InvalidConstruction When the argument has no admitted word form
     */
    public function word(?OptionArgument $argument): ?string
    {
        if ($argument instanceof TypeName) {
            $word = $argument->designation;
            Check::input($word instanceof NamedDesignation && $word->modifiers === [] && !$argument->setOf
                && $argument->array === null && count($word->name->parts) === 1, 'A native view option needs a single word.');

            return strtolower($word->name->parts[0]->value);
        }

        return match (true) {
            $argument === null => null,
            $argument instanceof Toggle, $argument instanceof StringConstant => strtolower($argument->value),
            $argument instanceof KeywordWord, $argument instanceof Word => strtolower($argument->word->value),
            default => throw new InvalidConstruction('CREATE VIEW requires a supported native option value.'),
        };
    }
}
