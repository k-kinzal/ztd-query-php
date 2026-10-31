<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * One argument of a call written as `name(...)`, with the alias a loadable function receives as the name of the argument.
 *
 * Without an alias a loadable function sees the text of the argument as its
 * name; native functions reject an alias. The call that holds the argument
 * derives its expression. Unaliased arguments can retain a validated spelling
 * layout for diagnostic names. The layout must express the same operand under
 * its language profile; neither source SQL nor parser nodes are retained.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/udf-arguments.html.
 *
 * @visibility public
 * @example Reading an argument and its alias
 *     $argument = new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Statement\Identifier\Name('total'));
 *     [$argument->expression->name->value, $argument->alias?->value] // => ['a', 'total']
 * @example Preserving the name used by an argument diagnostic
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT ABS(cast('123' as decimal(1))) AS a");
 *     $query->query->items[0]->expression->arguments[0]->layout->text() // => "cast('123' as decimal(1))"
 */
final class CallArgument implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The language interpreting a noncanonical argument name, absent without a layout.
     */
    public readonly ?\SqlSemantics\Contract\LanguageProfile $spellingProfile;

    /**
     * @param Scalar $expression The argument expression
     * @param Name|null $alias The alias written after it
     * @param AliasMark $mark Whether the alias is written after AS
     * @param \SqlSemantics\Statement\Spelling\Layout|null $layout Validated noncanonical spelling used by argument diagnostics
     * @param \SqlSemantics\Contract\LanguageProfile|null $spellingProfile The profile interpreting the layout
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the mark does not fit the alias or the layout contradicts the expression
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null, public readonly AliasMark $mark = AliasMark::As, public readonly ?\SqlSemantics\Statement\Spelling\Layout $layout = null, ?\SqlSemantics\Contract\LanguageProfile $spellingProfile = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($mark !== AliasMark::Equals && ($alias !== null || $mark === AliasMark::As), 'An argument alias is written with or without AS, and an argument without alias has no alias mark.');
        $item = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($expression, $alias, $layout, $mark, $spellingProfile);
        $this->spellingProfile = $item->spellingProfile;
    }

    /**
     * Writes the expression and its alias.
     */
    public function render(Output $out): void
    {
        $out->layout($this->layout, $this->expression, $this->spellingProfile?->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651);
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
