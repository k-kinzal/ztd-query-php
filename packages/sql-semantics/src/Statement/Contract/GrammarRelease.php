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
            self::MySql5651 => '5c74252eb4f0712d4c3079a717fe1ea58daad61caf1f91e0fcc68be9c8b0484c',
            self::MySql5744 => '565722d68e0953fd067ad68385b8094faa891e38c0788aba26c8eb9a1225b373',
            self::MySql8044 => '1c0d1ba764dc0bcf28195cec376ba1000bc3a478848015552c365004e41b3181',
            self::MySql810 => 'ee3222ae0d4591c5e76f36ee225607beef642a5a69c553a2a9a7f606af30d375',
            self::MySql820 => 'cea373d3ea7f71946ed794ec940cae0f03c29429579677643b993cafc44f22c2',
            self::MySql830 => '6abe806767da9c0cf6d01a2e7fb88e9a021eceb50dfacdfe928b4f75b2014f64',
            self::MySql847 => '3fd0c8ebfaff4a9d0d050dd8abdad4c4a58bf48c05102a0daa7f573c46d3d620',
            self::MySql901 => '9edc0152dad50a2f62f35146285616c0378b3ddf15518319ed8bbc817d74c3f5',
            self::MySql910 => '196b535f4cba376925d6bc2e4fd9a7d0fee4d727ee6ae11e06bc027453b9803c',
            self::PostgreSql166 => '134b036758547d76af2c2e4c0173b81e3186ffb4a01c04345734d412975f12cb',
            self::PostgreSql172 => '29ca6b943c312b5ee95006b6ea908eb8b2df1cc9c45907a525c4f6b1493371e9',
            self::Sqlite3472 => '6f00dd6a70fc24f51e9d6f33a8f9ec10319e15a95623dbcf601c504789b06d6c',
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
