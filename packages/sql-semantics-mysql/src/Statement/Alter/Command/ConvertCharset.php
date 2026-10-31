<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * `CONVERT TO CHARACTER SET name | DEFAULT [COLLATE name]`: a request to convert every character column and the table default.
 *
 * Mirrors PT_alter_table_convert_to_charset. DEFAULT means the character
 * set selected by the session variable character_set_database. CHARACTER SET, CHAR SET and CHARSET are synonyms; the written form is retained.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-character-set.
 *
 * @visibility public
 * @example Converting a table to utf8mb4
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t CONVERT TO CHARSET utf8mb4');
 *     [$alter->commands[0]->charset->name?->value, $alter->toString()] // => ['utf8mb4', 'ALTER TABLE t CONVERT TO CHARSET utf8mb4']
 */
final class ConvertCharset implements AlterCommand
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param CharsetName $charset The character set, or DEFAULT
     * @param CollationName|null $collation The COLLATE clause, when written
     * @param \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm $form The written named character-set form
     */
    public function __construct(public readonly CharsetName $charset, public readonly ?CollationName $collation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm $form = \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharacterSet)
    {
        \SqlSemantics\Diagnostic\Check::input($form->named(), 'A conversion names its character set.');
    }

    /**
     * Resolves character set and collation names in their written order.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $facts = new ElementFacts();
        $facts->encoding($this->charset, $derivation, $this);
        if ($this->collation !== null) {
            $facts->encoding($this->collation, $derivation, $this);
        }
        if (in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $this->deriveCollation($derivation, $this);
        }
    }

    /**
     * Checks the relationship between known encoding names at the enclosing boundary.
     *
     * Modern ALTER TABLE finishes reading its action list before comparing the
     * encodings. Legacy releases compare them when the conversion is read.
     *
     * @param \SqlSemantics\Statement\Node $owner The conversion or enclosing ALTER TABLE which owns this phase
     * @example Finding incompatible known names
     *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin');
     *     $alter->warnings()[0]->message() // => "COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8mb4'"
     */
    public function deriveCollation(Derivation $derivation, \SqlSemantics\Statement\Node $owner): void
    {
        $settings = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context);
        $charset = $this->charset->name === null ? ($settings->databaseCollation ?? $settings->schema($derivation->context->searchPath[0]->value))->charset : \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($this->charset->name->value);
        $collation = $this->collation?->name === null ? null : \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::forName($this->collation->name->value, $derivation->context->profile->grammar);
        if ($charset !== null && $collation !== null && $charset !== $collation->charset) {
            $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch($collation->name, $charset->name);
            $derivation->report($problem);
            $phase = in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)
                ? \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax : \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization;
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true, $phase), $owner);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('CONVERT', 'TO')->keyword(...explode(' ', $this->form->value))->node($this->charset);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
    }
}
