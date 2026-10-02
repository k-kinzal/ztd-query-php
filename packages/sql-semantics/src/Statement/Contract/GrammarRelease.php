<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Contract;

/**
 * Exact shipped grammar and lexical artifacts, pinned independently of a mutable parser.
 *
 * Digests identify artifacts, not semantic completeness. Changing a shipped artifact
 * requires a deliberate profile revision and review of its semantic rules.
 * @visibility public
 * @example Selecting a fixed grammar release
 *     \SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472->value // => 'sqlite-3.47.2'
 */
enum GrammarRelease: string
{
    case MySql5651 = 'mysql-5.6.51';
    case MySql5744 = 'mysql-5.7.44';
    case MySql8044 = 'mysql-8.0.44';
    case MySql810 = 'mysql-8.1.0';
    case MySql820 = 'mysql-8.2.0';
    case MySql830 = 'mysql-8.3.0';
    case MySql847 = 'mysql-8.4.7';
    case MySql901 = 'mysql-9.0.1';
    case MySql910 = 'mysql-9.1.0';
    case PostgreSql166 = 'pg-16.6';
    case PostgreSql172 = 'pg-17.2';
    case Sqlite3472 = 'sqlite-3.47.2';

    /**
     * Identifies the exact compiled grammar artifact.
     */
    public function grammarDigest(): string
    {
        return match ($this) {
            self::MySql5651 => 'e937f61b8bda85c3c0e22dda4e455e8b0b629b8aef8b37cdcae578dffaff7dae',
            self::MySql5744 => '5c1beff7665a36ef5858f5421d6cbd913b009b38e96c95da4b27c4fd520843ec',
            self::MySql8044 => '13855b513f1122b1f55f384f2fc8107dfc8f564b49a7ad06f4a4d357181a40fe',
            self::MySql810 => '71d71cda6854859ef02131c4bcf33e568d0206f73e335fe255905efbc3e10711',
            self::MySql820 => '8ccf447c83426627cb36bf14d4252c93b08610fe50abd3dbb97e20571f5031ac',
            self::MySql830 => 'c24e3c5317630235a2adb3991b33c45c67279d52a716036fae3b24ac6baf3d8f',
            self::MySql847 => '713d6e606abb1d93606a8b7017e3202a11d42df19afdc06a2ef2479dc6febe52',
            self::MySql901 => '3aa21c6df4d47e32869a8cce85c1cf6487762020548097460b23f08ac8fb09c0',
            self::MySql910 => '1515f11725e7da94fca69f4a9d1088c2e50a705a1efecef8d671d0da451301db',
            self::PostgreSql166 => '7d55fbcd39febf445fd8d8a4a7884486f1a30d6e62ab1a10bea346ae27c8af90',
            self::PostgreSql172 => '82294937323322564a269aa095eaced2a64b5f2b9bf547d32b273bef6eec15b6',
            self::Sqlite3472 => '579e2b88d7ca99a261b9faeae3401a2344850b91ad3c276b72ab51e608633884',
        };
    }

    /**
     * Identifies the exact keyword artifact used for lexical interpretation.
     */
    public function keywordDigest(): string
    {
        return match ($this) {
            self::MySql5651 => 'd72ca12213a649ff9ee141f9793adf2be071ce6815bfe7452b17eee43f70d0e3',
            self::MySql5744 => '677ad2715cacc33c9d04257b21fbbe9a7565eae7e1346a476833db589eb4d886',
            self::MySql8044 => '785da584bb6b992fbbfebdfaa64162852bf0eb11dfed2b33e910b7532f7e84f3',
            self::MySql810 => 'b3d307a2cab9bda5c43b1e9c5acaaa4dc410a7d08eab5b0f05436f20790ad014',
            self::MySql820 => '7866a9e3d0589d517daa086b9af6aa2bc01b384106b770203393dac58616f5db',
            self::MySql830 => 'fdcf29ebd2b46c170be641e26010264da055ae36e5df3b193b04dce49c118f77',
            self::MySql847 => '08e98a78eff1a39b384179bbfe0ffd02c481e0aa949be5473f91657577e3a2e7',
            self::MySql901 => '61c5e3532500fc38ec24572a36560372c2092eb0f1d6a07d40af927a5739d0a9',
            self::MySql910 => '35b8ba0f89dcbb33aee4933662f2325adb0bf6f1a82138b5d492a8fa6db0cd1b',
            self::PostgreSql166 => '30153c013e398cc0385ae9ce741c15f7436a53b3fc3bb2eee0b0bcc388efde49',
            self::PostgreSql172 => '6f474037d9223516399697bd44984f2ebe9eea536e037e3876c372c32e775ceb',
            self::Sqlite3472 => '8276cc16f199de0461eafc245833b8a1808f17a133ea6587bfef9f2d18f69b90',
        };
    }

    /**
     * Names the database whose lexical and semantic rules interpret this artifact.
     */
    public function database(): string
    {
        return match ($this) {
            self::MySql5651, self::MySql5744, self::MySql8044, self::MySql810,
            self::MySql820, self::MySql830, self::MySql847, self::MySql901,
            self::MySql910 => 'mysql',
            self::PostgreSql166, self::PostgreSql172 => 'postgresql',
            self::Sqlite3472 => 'sqlite',
        };
    }
}
