<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use InvalidArgumentException;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Statement\Contract\GrammarRelease;
use SqlSemantics\Statement\Contract\LanguageProfile;
use SqlSemantics\Statement\Contract\LexicalSettings;
use SqlSemantics\Statement\Contract\ParameterStyle;

/**
 * Establishes an immutable profile from the actual configured parser artifacts.
 * @visibility SqlSemantics
 */
final class ProfileReader
{
    /**
     * A familiar version label cannot conceal a different installed grammar artifact.
     * @throws InvalidArgumentException When the selected artifacts have no matching profile
     */
    public function read(string $version, string $lexicalSettings = '', ParameterStyle $parameters = ParameterStyle::Native): LanguageProfile
    {
        $release = GrammarRelease::tryFrom($version);
        if ($release === null) {
            throw new InvalidArgumentException('No semantic profile for the selected grammar release.');
        }
        $artifact = (new VersionRegistry())->resolve($release->database(), $release->value);
        if (hash_file('sha256', $artifact->tablePath) !== $release->grammarDigest()
            || hash_file('sha256', $artifact->keywordPath) !== $release->keywordDigest()) {
            throw new InvalidArgumentException('Installed grammar artifacts do not match the fixed semantic profile.');
        }
        return new LanguageProfile($release, new LexicalSettings($lexicalSettings), $parameters);
    }
}
