<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\LoadData;

use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlValueRenderer;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Row Projector.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class RowProjector
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly MySqlValueRenderer $valueRenderer)
    {
    }
    /**
     * @param list<string> $targets
     * @param array<string, string> $setOperations
     * @param list<string|null> $values
     * @return array<string, string>
     */
    public function projectRow(array $targets, array $setOperations, array $values): array
    {
        $row = [];
        $variables = [];
        foreach ($targets as $index => $target) {
            $hasValue = array_key_exists($index, $values);
            $value = $values[$index] ?? null;
            if ($target[0] === '@') {
                $variables[strtolower(substr($target, 1))] = $hasValue ? $value : null;
                continue;
            }
            $row[$target] = $hasValue ? ($value === null ? 'NULL' : $this->valueRenderer->renderValue($value)) : 'DEFAULT';
        }
        foreach ($setOperations as $column => $expression) {
            $row[$column] = $this->substituteVariables($expression, $variables);
        }

        return $row;
    }

    /**
     * @param array<string, string|null> $variables
     */
    public function substituteVariables(string $expression, array $variables): string
    {
        $stream = SqlTokenStream::tokenize($expression, MySqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $result = '';
        $cursor = 0;
        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; $index++) {
            $token = $tokens[$index];
            if ($token->text !== '@') {
                continue;
            }
            $previous = $tokens[$index - 1] ?? null;
            if ($previous !== null && $previous->text === '@') {
                continue;
            }
            $identifier = $stream->identifierAt($index + 1);
            if ($identifier === null) {
                continue;
            }
            $name = strtolower($identifier['name']);
            if (!array_key_exists($name, $variables)) {
                continue;
            }
            $last = $tokens[$identifier['next'] - 1];
            $result .= substr($expression, $cursor, $token->offset - $cursor);
            $result .= ($variables[$name] === null ? 'NULL' : $this->valueRenderer->renderValue($variables[$name]));
            $cursor = $last->endOffset();
        }

        return $result . substr($expression, $cursor);
    }
}
