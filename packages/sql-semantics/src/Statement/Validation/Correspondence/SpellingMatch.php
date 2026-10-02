<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Expression\Rendering as R;

/**
 * Compares bounded presentation values without confusing them with expression SQL.
 * @visibility SqlSemantics
 */
final class SpellingMatch
{
    /**
     * Equivalent immutable layouts may have distinct PHP identities.
     */
    public static function same(R\SqliteUnaryLayout|R\SqliteBinaryLayout|R\SqliteCaseLayout|R\SqliteCaseArmLayout|R\SqliteElseLayout|null $expected, R\SqliteUnaryLayout|R\SqliteBinaryLayout|R\SqliteCaseLayout|R\SqliteCaseArmLayout|R\SqliteElseLayout|null $actual): bool
    {
        return match (true) {
            $expected instanceof R\SqliteUnaryLayout => $actual instanceof R\SqliteUnaryLayout && [$expected->operator, $expected->lexeme, $expected->after, $expected->groupOperand] === [$actual->operator, $actual->lexeme, $actual->after, $actual->groupOperand],
            $expected instanceof R\SqliteBinaryLayout => $actual instanceof R\SqliteBinaryLayout && [$expected->operator, $expected->lexeme, $expected->before, $expected->after, $expected->groupOperands] === [$actual->operator, $actual->lexeme, $actual->before, $actual->after, $actual->groupOperands],
            $expected instanceof R\SqliteCaseLayout => $actual instanceof R\SqliteCaseLayout && [$expected->caseKeyword, $expected->endKeyword, $expected->baseGap, $expected->endGap] === [$actual->caseKeyword, $actual->endKeyword, $actual->baseGap, $actual->endGap],
            $expected instanceof R\SqliteCaseArmLayout => $actual instanceof R\SqliteCaseArmLayout && [$expected->whenKeyword, $expected->thenKeyword, $expected->before, $expected->afterWhen, $expected->beforeThen, $expected->afterThen] === [$actual->whenKeyword, $actual->thenKeyword, $actual->before, $actual->afterWhen, $actual->beforeThen, $actual->afterThen],
            $expected instanceof R\SqliteElseLayout => $actual instanceof R\SqliteElseLayout && [$expected->keyword, $expected->before, $expected->after] === [$actual->keyword, $actual->before, $actual->after],
            default => $actual === null,
        };
    }
}
