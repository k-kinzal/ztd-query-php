<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FileNode>
 */
final class ForbiddenCommentRule implements Rule
{
    private const PHPSTAN_IGNORE_PATTERN = '/@phpstan-ignore(?:-line|-next-line)?\b/';
    private const INFECTION_IGNORE_ALL_PATTERN = '/@infection-ignore-all\b/';

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        unset($node);

        $path = $scope->getFile();

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $source = file_get_contents($path);
        if ($source === false) {
            return [];
        }

        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (\ParseError) {
            return [];
        }

        $errors = [];

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }

            [$tokenType, $text, $line] = $token;

            if (!in_array($tokenType, [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (preg_match(self::PHPSTAN_IGNORE_PATTERN, $text) === 1) {
                $reportedLine = $this->reportedLineForIgnoreComment($text, $line);
                $commentContent = $this->truncateComment($text);
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'phpstan-ignore comments are prohibited: "%s". Suppressing static analysis hides real problems and weakens CI. Fix the underlying issue instead. If truly essential, ask a human operator to add an ignoreErrors entry in phpstan.neon.',
                        $commentContent
                    )
                )
                    ->identifier('customRules.phpstanIgnoreComment')
                    ->line($reportedLine)
                    ->build();
            }

            if (preg_match(self::INFECTION_IGNORE_ALL_PATTERN, $text) === 1) {
                $commentContent = $this->truncateComment($text);
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'infection-ignore-all comments are prohibited: "%s". Ignoring all mutations hides real quality regressions and weakens CI. Refactor tests and production code so mutation testing can run without this suppression.',
                        $commentContent
                    )
                )
                    ->identifier('customRules.infectionIgnoreAllComment')
                    ->line($line)
                    ->build();
            }

            if ($tokenType === T_COMMENT && $this->isDoubleSlashComment($text)) {
                $reportedLine = $this->reportedLineForIgnoreComment($text, $line);
                $commentContent = $this->truncateComment($text);
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'Line comments using // are prohibited: "%s". Refactor to make the comment unnecessary, or delete it. If truly essential, ask a human operator to add an ignoreErrors entry in phpstan.neon.',
                        $commentContent
                    )
                )
                    ->identifier('customRules.doubleSlashComment')
                    ->line($reportedLine)
                    ->build();
            }
        }

        return $errors;
    }

    private function isDoubleSlashComment(string $text): bool
    {
        return str_starts_with($text, '//');
    }

    private function reportedLineForIgnoreComment(string $text, int $line): int
    {
        if (str_contains($text, '@phpstan-ignore-line')) {
            return $line + 1;
        }

        return $line;
    }

    private function truncateComment(string $text): string
    {
        $trimmed = trim($text);
        if (mb_strlen($trimmed) > 80) {
            return mb_substr($trimmed, 0, 80) . '...';
        }

        return $trimmed;
    }
}
