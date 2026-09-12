<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Lemon;

use RuntimeException;

/**
 * Selects source branches using the %if/%ifdef/%ifndef/%else/%endif directives from tool/lemon.c.
 */
final class LemonPreprocessor
{
    /**
     * @param LemonCondition $condition The build's fixed set of named definitions
     */
    public function __construct(private readonly LemonCondition $condition = new LemonCondition())
    {
    }

    /**
     * Preserves line count while omitting source inactive in the target parser build.
     * @throws RuntimeException When conditional directives are unbalanced or malformed
     */
    public function process(string $input): string
    {
        $active = true;
        $stack = [];
        $output = [];
        foreach (explode("\n", $input) as $line) {
            if (preg_match('/^%(if|ifdef|ifndef|else|endif)\b(.*)$/', $line, $match) !== 1) {
                $output[] = $active ? $line : '';
                continue;
            }
            $directive = $match[1];
            if ($directive === 'else' || $directive === 'endif') {
                $frame = array_pop($stack);
                if ($frame === null || ($directive === 'else' && $frame[2])) {
                    throw new RuntimeException('Unmatched Lemon directive: %' . $directive);
                }
                $active = $frame[0];
                if ($directive === 'else') {
                    $stack[] = [$frame[0], $frame[1], true];
                    $active = $frame[0] && !$frame[1];
                }
            } else {
                $selected = $active && $this->condition->evaluate(trim($match[2]));
                $selected = $directive === 'ifndef' ? !$selected : $selected;
                $stack[] = [$active, $selected, false];
                $active = $active && $selected;
            }
            $output[] = '';
        }
        if ($stack !== []) {
            throw new RuntimeException('Unterminated Lemon conditional directive.');
        }
        return implode("\n", $output);
    }
}
