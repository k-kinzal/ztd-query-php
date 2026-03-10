<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\TerminationLengthComputer as TerminationLengthComputerContract;
use SqlFaker\Contract\TerminationLengths;

final class TerminationLengthComputer implements TerminationLengthComputerContract
{
    public function compute(Grammar $grammar): TerminationLengths
    {
        $infinity = PHP_INT_MAX;

        $lengths = [];
        foreach ($grammar->rules as $ruleName => $_rule) {
            $lengths[$ruleName] = $infinity;
        }

        $changed = true;
        while ($changed) {
            $changed = false;

            foreach ($grammar->rules as $ruleName => $rule) {
                $best = $lengths[$ruleName];

                foreach ($rule->alternatives as $alternative) {
                    $alternativeLength = 0;
                    $valid = true;

                    foreach ($alternative->symbols as $symbol) {
                        if (!$symbol->isNonTerminal) {
                            $alternativeLength += 1;
                            continue;
                        }

                        $symbolLength = $lengths[$symbol->name] ?? $infinity;
                        if ($symbolLength === $infinity) {
                            $valid = false;
                            break;
                        }

                        $alternativeLength += $symbolLength;
                    }

                    if ($valid && $alternativeLength < $best) {
                        $best = $alternativeLength;
                    }
                }

                if ($best !== $lengths[$ruleName]) {
                    $lengths[$ruleName] = $best;
                    $changed = true;
                }
            }
        }

        foreach ($lengths as $ruleName => $length) {
            if ($length === $infinity) {
                $lengths[$ruleName] = 1;
            }
        }

        return new TerminationLengths($lengths);
    }
}
