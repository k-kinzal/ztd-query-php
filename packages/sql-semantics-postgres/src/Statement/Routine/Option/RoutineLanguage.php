<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;

/**
 * `LANGUAGE name`: the language the routine is written in.
 *
 * The name is a word or a string constant; the server looks it up as written
 * after decoding. Without the option, a routine with an SQL-standard body is
 * in language `sql`.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility SqlSemantics
 * @example Reading the language name
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineLanguage(new \SqlSemantics\Platform\PostgreSql\Statement\Option\Word(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('plpgsql')));
 *     $option->name() // => 'plpgsql'
 */
final class RoutineLanguage implements RoutineOption
{
    use Snapshot;

    /**
     * @param Word|StringConstant $language The language name
     */
    public function __construct(public readonly Word|StringConstant $language)
    {
    }

    /**
     * Answers the language name the server looks up.
     */
    public function name(): string
    {
        return $this->language instanceof Word ? $this->language->word->value : $this->language->value;
    }

    /**
     * Tells that ALTER does not accept the option.
     */
    public function alterable(): bool
    {
        return false;
    }

    /**
     * Answers `language`.
     */
    public function setting(): string
    {
        return 'language';
    }

    /**
     * Derives nothing: a name holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes LANGUAGE and the name.
     */
    public function render(Output $out): void
    {
        $out->keyword('LANGUAGE')->node($this->language);
    }
}
