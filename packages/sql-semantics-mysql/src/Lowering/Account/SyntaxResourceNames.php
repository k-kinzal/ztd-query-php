<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Lexer\SourceException;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Platform\MySql\Rules\Identifiers;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Retains resource-group identifier conversion before a later syntax refusal.
 *
 * The identifier is converted as it is read, even if the command never completes.
 * Only a decoded name and its completion offset escape this input-boundary scan.
 * Observed on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxResourceNames
{
    /**
     * Returns conversion notices for completed names before the refusal boundary.
     *
     * @return list<array{int, NameConversion}>
     */
    public function completed(ReductionParser $parser, string $statement, int $end): array
    {
        try {
            $tokens = $parser->tokenize(substr($statement, 0, $end));
        } catch (SourceException) {
            return [];
        }
        $previous = [];
        $notices = [];
        foreach ($tokens as $token) {
            if ($token->name === 'IDENT_QUOTED' && count($previous) === 3 && in_array($previous[0], ['CREATE', 'ALTER', 'DROP', 'SET_SYM'], true) && $previous[1] === 'RESOURCE_SYM' && $previous[2] === 'GROUP_SYM') {
                $name = new Name((new Identifiers())->decode($token->text));
                if (ResourceGroupName::decoded($name) !== $name->value) {
                    $notices[] = [$token->offset + strlen($token->text), new NameConversion($name)];
                }
            }
            $previous[] = $token->name;
            $previous = array_slice($previous, -3);
        }

        return $notices;
    }
}
