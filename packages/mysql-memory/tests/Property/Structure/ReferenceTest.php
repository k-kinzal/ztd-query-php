<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Reference;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Reference submission binds only typed parameter occurrences, preserving other spellings.
 */
#[CoversNothing]
#[Small]
final class ReferenceTest extends TestCase
{
    /**
     * Quoted markers and similarly named aliases are values and identifiers, not inputs.
     */
    public function testSqlBindsMixedMarkersWithoutReplacingQuotedValues(): void
    {
        $node = new QueryRoot(new Select([], [new SelectExpression(new Parameter(':v')), new SelectExpression(new Parameter()), new SelectExpression(new StringLiteral([':v?']), new Name(':v'))]));
        $profile = new LanguageProfile(GrammarRelease::MySql847, parameters: ParameterStyle::Named);

        self::assertSame("SELECT NULL, NULL, ':v?' AS `:v`", (new Reference())->sql($node, $profile));
    }

    /**
     * The selected lexical mode controls reference rendering as well as direct execution.
     */
    public function testSqlPreservesTheSelectedEscapeMode(): void
    {
        $node = new StringLiteral(['\\']);
        $profile = new LanguageProfile(GrammarRelease::MySql847, new LexicalSettings('NO_BACKSLASH_ESCAPES'));

        self::assertSame("SELECT '\\'", (new Reference())->sql($node, $profile));
    }
}
