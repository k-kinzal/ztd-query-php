<?php

declare(strict_types=1);

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;

function nativeBoundaryContract(string $dialect): array
{
    $records = match ($dialect) {
        'sqlite' => [
            ['sqlite-rename-boundaries-v2.json', 'f6fccd438228146e360b5dffb17aa17e16342a09069faef53d2a7b05ad08ddf3'],
            ['sqlite-create-index-boundaries-v1.json', '0a0c16a74cda0d7598ad696581a4db0cbbe9050423e4512a75e0d042f54a2769'],
            ['sqlite-drop-index-boundaries-v2.json', '3dede0e8cf427de163c80b2fc736d88fc7da708899f246a5bab6feacb792538b'],
            ['sqlite-drop-column-boundaries-v1.json', 'cdb8c0184c1950dc37897c17c43a427e17216eceb66d27a54ed7beb0bb43b01e'],
            ['sqlite-add-column-boundaries-v5.json', 'b33bcc1f10e3cca71f4dfc4ce1b5e2098a4fc817abfb41acd78c212b5c2c7c76'],
            ['sqlite-conflict-boundaries-v3.json', 'e213c39415e301abdf7684710d923ac82b30de9e2ce9b0e3ae361e5297291e84'],
            ['sqlite-query-scope-boundaries-v9.json', 'bda4f3f5e8f81d04f57629a4f47e81cb2ba2f8a5b2761ffaceb7e42692f68006'],
            ['sqlite-grouped-boundaries-v5.json', '5e0e16fc9ac50cd6421ad6aa85a9b063b9681e90beb2b7acc021c41afe796f3a'],
            ['sqlite-wildcard-boundaries-v4.json', '299d27d56b6190fbb5bc9e1c89704579155cdb1bf083b0bae5aa230577911c00'],
            ['sqlite-insert-slots-boundaries-v5.json', '55d984c88686949361c74660945cc67450c02a86b6a69c53aec6f079f46f87c0'],
            ['sqlite-numeric-boundaries-v2.json', 'b625f2484ffd6dcd30ba27f24a9930e09061b2a508fa0eedb32ef36ceab6604b'],
            ['sqlite-scalar-boundaries-v23.json', 'e6cbd79c29f37edf79a0f57ee5eda919bd9f1461cb7b8a633b69b89d7c28151f'],
            ['sqlite-default-boundaries-v13.json', '41a0a4d1dfaa73edf40cb5b8fafb702130fc0ce17a930661f13aaa3e03936b29'],
            ['sqlite-drop-view-boundaries-v3.json', 'c1d6249210e4b7640690ec779b203fa01b7980392cecc88f5151f07b387bcdbe'],
            ['sqlite-reindex-boundaries-v2.json', '3fb402c7fac25484836e5d50c33ea03e7f55cd6f3d5511ffe6327a260177ba80'],
            ['sqlite-analyze-statistics-boundaries-v1.json', '02dbd2404a342c2048199c37f0904e2fa8f0c6e4ca31347549409f8d4b2adb7d'],
            ['sqlite-flag-pragmas-boundaries-v11.json', '60d2179bcbd7d5023e2eceb7b09f3e84d946d1a2bfe3c93e0fdbcf897b3786b8'],
            ['sqlite-integrity-audit-boundaries-v2.json', 'e5f26cdfd45c337fab39a241a32720e8f3fe92da03d77e47ba34f2784530bbfb'],
            ['sqlite-header-pragmas-boundaries-v1.json', '0436a6d2d85f4c6b983fb91fb823cc2ad686e8df40e9fe0af1e07fe065c5da2a'],
            ['sqlite-vacuum-boundaries-v1.json', '58d3adeccb39cbc65fa0f7405f3bdddee134c0d0f0a56282101e90fe47fcb9c2'],
            ['sqlite-connection-commands-boundaries-v1.json', '17c3712053a125b7204dd0c9d95f0b52ddcb0f36c15f7e43122c898c000b9d50'],
            ['sqlite-page-pragmas-boundaries-v1.json', 'ed7e3038312119441140352f56dd43b274520c30903b6a4dfef5e912ab8e4c21'],
            ['sqlite-reclaim-pragmas-boundaries-v1.json', 'e7c3d5b7a1822b1d5bace8754a23892a324b1d433609b57f35e7ac529a05b6fa'],
            ['sqlite-cache-pragmas-boundaries-v1.json', '60c4f0af764fdd137ed5b34c7cf9d9812506d3901d1017a6443f28ce26202597'],
            ['sqlite-audit-triggers-boundaries-v5.json', '1116347e6d71b6a5427583c0248413c56da7ec96c158f4cc9aad6a22c14370e3'],
            ['sqlite-drop-triggers-boundaries-v1.json', 'ddfc78cbd257a9fa1b449c08f6e85eb3f1d114eca6529256636a42babbd9634c'],
            ['sqlite-foreign-key-audit-boundaries-v1.json', '5bb678946a4c64f0af472a44d15418550166719cb920683f240fd3dd9991b085'],
            ['sqlite-index-metadata-boundaries-v1.json', '321a0506e38a0841cdefa822858153626c4c2697a529eb78530719b21d735dec'],
            ['sqlite-table-metadata-boundaries-v2.json', 'fbef9194763edebc3700e0b8e57294b477eb0f6cf1c84ead54022242554bd2e7'],
            ['sqlite-connection-inventory-boundaries-v1.json', 'e5b35533973bfda256d90e2cc80f3bcb90fb8a3ec504bcf516f47c02685c9c48'],
            ['sqlite-create-view-boundaries-v4.json', '98fe27cbdbf1c468c1c633c8449ad89002257aaa2826fd1e9af60f114f11caaa'],
            ['sqlite-mutation-returning-boundaries-v3.json', 'bf2ac57e17e98eeedb1b078271a5f02c7daef17aa3c33339af6e7909d64d350b'],
        ],
        'mysql', 'postgres' => [
            ...($dialect === 'postgres' ? [
                ['postgres-mutation-returning-boundaries-v2.json', '082d7dd3af450a824ec217de545754e2f034eb33027dbb342456531b31dcefc3'],
            ] : []),
            ['native-boundaries-v31.json', '3fca83f52f260bf9f43b909228eeed7f00d77534d2183368b530673364738b55'],
            ['native-insert-query-boundaries-v4.json', '67796e3b509a778308dcdf70c04ebcf7d455e9014faac77c426f6ee105290373'],
        ],
    };
    $records[] = ['identifier-boundaries-v1.json', 'e526dfe9c9f107bf37e8aa1a38a006e7615f9dcf912d2087d564c19b500c7ab0'];
    $rules = [];
    foreach ($records as [$name, $digest]) {
        $path = __DIR__ . '/' . $name;
        requireShape(is_file($path) && hash_file('sha256', $path) === $digest, "Boundary contract differs from its recorded revision: $name");
        $additional = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['rules'];
        requireShape($additional !== [] && array_intersect_key($additional, $rules) === [], "Boundary contract has no distinct rules: $name");
        $rules += $additional;
    }

    $amendments = match ($dialect) {
        'mysql' => [
            ['mysql-from-list-boundaries-v1.json', '75520d651cdde8992dcf05de45be54b9cb5d44d3af3f3536b612d6e83d7a443d'],
            ['mysql-text-literal-boundaries-v1.json', '46a77328b7d16609bd966a6ac2c1086a7d62e708606f0c7774d22d2a756fdb12'],
            ['mysql-varchar-slot-boundaries-v1.json', '07b2a4d8f69b29a9739f3afceaffcb2fe676c2bb64ceef286fd7c3f4df0bc03d'],
            ['mysql-varchar-presence-boundaries-v1.json', 'fffbc606bf20d51b751ac695b96badb2b64a4ee91c0e3ab9338afc0973240045'],
            ['mysql-varchar-label-boundaries-v1.json', 'ddfd1e58cc81339e59694a5f7250d46c10b482c1d37f4a201a8bbd17835c2fe9'],
            ['mysql-varchar-comparison-boundaries-v1.json', '8d997095c80064880f6c6e3ef6e8c43c90ea4490a909eeb5ef64ce545963b149'],
            ['mysql-varchar-assignment-boundaries-v1.json', '17bf1801f1d93fc7a69ae05645249e9d4483c0cfc0b62240cb3aa278e586714a'],
            ['mysql-varchar-insert-boundaries-v1.json', 'cb98b50df5693008bd453adbf33981fc46f465162deede996663ad8f25a4bf2d'],
            ['mysql-varchar-insert-query-boundaries-v1.json', '9c115dd302a146cc12a56b8a268e3dba385a2c27a2fed8e366c37af0b9a1fd7a'],
            ['mysql-varchar-count-boundaries-v1.json', 'dd89e17397c9f9e6abe9f73b9a4710b1bc91eb09561c035ac973d860552a45bc'],
            ['mysql-varchar-extrema-boundaries-v1.json', 'c2c47d6e7d9a220b5ffc43c3e9c9b13bbcea824736c17d10e729eedc46754cf0'],
            ['mysql-explicit-text-collation-boundaries-v1.json', '6bfb85c25b4dbd401b4ede0577d7fcd8031bbb07c835064552a1525e7d772a6d'],
            ['mysql-derived-varchar-boundaries-v1.json', 'bed891c263a93d0c62cbcfec908fa171a593d6a55fec81020856a2d94b9b97d7'],
            ['mysql-create-varchar-boundaries-v1.json', 'cca62d6472498414b159b7934303e1250ddcda6103dc2a4f25460dee572b741d'],
            ['mysql-create-signed-boundaries-v1.json', 'daff522af411c8dac52a3d7d14c477483842c67b572683ea3b7e2bc5274693cf'],
            ['mysql-create-primary-boundaries-v2.json', '1657633bf073bbb6ff22462e5d1fc29e23de126d074eec5f3d6e56c506e384a3'],
            ['mysql-session-create-boundaries-v1.json', '565d4e596209260db690539e1d7144b788816603ba99e7d1fad0f0ca4b24a813'],
            ['mysql-create-like-boundaries-v1.json', '2518c94e814c4058aefd56a4dffd2bacc0f3f060a330d72f4221c6b1864ec1b4'],
            ['mysql-external-views-boundaries-v1.json', '06bbf4a62a3665c9bcbf35b073ca31d5e8d0d0017476112b168793f9a525aaf7'],
            ['mysql-with-projection-boundaries-v1.json', '187862930a636233380862b33a0c0c1d069367278cb6ef70e945d0fa0f642396'],
            ['mysql-grouped-having-boundaries-v1.json', 'b4418e87b51864f6fb59c01c39517e56d78fdbccb1dd7b1e91c781a9b40cb20e'],
            ['mysql-drop-view-boundaries-v1.json', '4efba4950adbee525f68c8efb1473faada655c9aba134cbca0c50a522e7e4dc6'],
            ['mysql-drop-view-boundaries-v2.json', 'a61772f65192e9ec90365cfc2c5b3744a476639f3a2ed2fb70aae24f3f9b72f5'],
            ['mysql-create-view-boundaries-v1.json', 'f1b3f058793119e58c0d97a70bd196c287f858e4cac779cb1553dde07e7a9a03'],
            ['mysql-create-table-as-boundaries-v1.json', '8da8eb5276c22a9415dadf9788f89efaadb5ed91cb4bde4fc6b4eab9ca6bb7d1'],
            ['mysql-create-table-as-boundaries-v2.json', 'daf1729386ff74d23f6060df1106305ffeb668c0f1be51cbca0fa3952cf8bb9c'],
            ['mysql-create-table-as-boundaries-v3.json', '1a9c612d2bc636c89aa66cb3f063f13c57c07cda295f28e17b6e123345eac904'],
            ['mysql-create-table-as-boundaries-v4.json', 'e5fefa6290e3877fe2587f9659728d75bdab8db9cffc91a44581feaca8f340b2'],
            ['mysql-ordered-table-rename-boundaries-v2.json', '47429c60cbcf1cad80643c5d3d48c59c30029ff2b049e9d994a535ac9bb492d3'],
            ['mysql-truncate-boundaries-v1.json', 'fc59f04c29a91fb5423d92e050c2018bd2636e6e53e45760a8fad2c4b4f52cee'],
            ['mysql-truncate-boundaries-v3.json', '80433ddbbd087315e5ea04ab0f08e589034e046e5009654956a4a8c147220636'],
            ['mysql-session-drop-boundaries-v2.json', '4b9c35cf442d0b74a444987e83719bacab728265fc6c5c437ff5270578311b0c'],
            ['mysql-session-table-sources-boundaries-v1.json', 'f7d52901e9c0e384623182b33b5d21290c977dd83af0ad469b87d058293e8e57'],
            ['mysql-innodb-declaration-width-boundaries-v1.json', 'e7c171a1be33301f6f958c16c03c7cec3dcaf2c5e2255c0037bda886be137894'],
            ['mysql-create-table-as-width-boundaries-v1.json', '12a13739e29e7129ad7e8d4beac9686040cc82b4b4598a7fc7002810ff4d2eb9'],
            ['mysql-create-btree-index-boundaries-v3.json', '4ab7ab260a3d18b982a41b0d38662d64ee43086302a05c171909b67cc8810771'],
            ['mysql-drop-native-index-boundaries-v13.json', '6c68d05024fadad12f83e85b90e59b87e4901317e7f5ca2275d5148683862713'],
        ],
        'postgres' => [
            ['postgres-create-text-boundaries-v1.json', 'f2a783062d780bf0fc95fdf27394c04238c992e524c58c3c19b02885538de98b'],
            ['postgres-create-signed-defaults-boundaries-v1.json', '40f1569f5715ae2b1addfb7927e4b6322a965ca877e65a6c37f85816a6b5961d'],
            ['postgres-create-boolean-boundaries-v1.json', 'ce3ed3ed39b2cb747ddf8ae50e3f27e4e093b78c20c4ef2e66e8610f090598b9'],
            ['postgres-query-boundaries-v9.json', '43a22d2bc34a130a0dc8e70842f9b1a04027c28284c2a459dde1c1dc9891cd95'],
            ['postgres-derived-text-boundaries-v1.json', '93ca7467eac18a2c3b8b29b19d6010e524b4dea52bcb48ff8e43e316e1cd30b6'],
            ['postgres-text-returning-boundaries-v1.json', '3cf2b6db5f68514d6ef1f34dfa3bb34746169fad138f7ac478f1214d4ace3565'],
            ['postgres-text-update-boundaries-v2.json', 'e38cfe97d78a85516a6175bca7965bfd06f22ffd7953c6f636d49cc910ab8b07'],
            ['postgres-text-insert-boundaries-v2.json', '8c2787a86847cf6b6e8db819769588e6dc94a5f3d2ed4dc176f00e97e75865cf'],
            ['postgres-text-insert-query-boundaries-v3.json', '99fe1cb60a230a76d15cb58a7c50bec6cc851d90188286d790e5e3ee6fdfac0b'],
            ['postgres-collated-text-boundaries-v1.json', '09835aa776c76916d1f6375f9fa44ad52dbe129a3c847ffb1a7f8ba334189995'],
            ['postgres-text-comparison-boundaries-v2.json', '3e7d4b2c945608b48c52a541b2cb85b474f4097a67475b489725d300aaed6114'],
            ['postgres-select-bindings-boundaries-v1.json', '9034762ce0de61e958cbba1eb314bdbde112920bb172cd0e87190f059dc4fc79'],
            ['postgres-boolean-fields-boundaries-v1.json', 'f4d06df0e8bfcca315a114851209270de7e19543bcc3d5b4ed8c9760db31545e'],
            ['postgres-boolean-returning-boundaries-v1.json', '37752e21d0c6ade880a6af530b1238d5ce335e5fcc663b3b4e1a378a5da560bb'],
            ['postgres-boolean-writes-boundaries-v1.json', 'b72bfce51bcb67617dbdb29eca44c831bd80d663bd7327f551b4b4345387c4b2'],
            ['postgres-boolean-write-inputs-boundaries-v1.json', '4d8fcc71a9c381dc6c3f10a9bda953ae8b799395e8b92d93ad713110a06ad683'],
            ['postgres-int8-fields-boundaries-v1.json', 'b3ca90d0760af06e4c1d5f4e23ccae910469a0a67800a5cd97bed38d591e5056'],
            ['postgres-source-order-boundaries-v2.json', 'd73ff098a2ea63b93a9af435895495a1a6d510f189c142d1385fc8daa01816b6'],
            ['postgres-signed-write-widths-boundaries-v1.json', '2c46b9338761c773015c9fd8ae43038678b787cd75ac1307682cdca8c3750297'],
            ['postgres-signed-comparison-widths-boundaries-v1.json', 'b63a75d5bd191d9f5f29c8edba5ddec0e6c1f883439d330d930b036643892080'],
            ['postgres-grouped-result-order-boundaries-v1.json', 'bea8a88bd761a870b6f9cac2d9af79dafb9bd0f1f4c2305ff8bef86e3daf5198'],
            ['postgres-external-source-kinds-boundaries-v1.json', 'e9d773c3db468973e5f2ff12b3b199cb4ac89f5dbb8f83a605894216d348a31f'],
            ['postgres-grouped-having-boundaries-v1.json', '0297473b3f47b36fb10ba90e55b5328000a001f9b1d8c0b001e362b272f4d5c3'],
            ['postgres-signed-range-widths-boundaries-v1.json', '28cd3860fb2bfbd1a7dea1897cd986b3db57b57376958dee3dc78256afa6782e'],
            ['postgres-with-projection-boundaries-v1.json', '6ecaa67c79a9468d8b723852a9da02e6fdfdab12de28d543aab8afea892e67e7'],
            ['postgres-drop-view-boundaries-v1.json', 'f06ff5f3c0110cedeca81290477aed0bf45f2f11c30e146de95420b2c6b1ea74'],
            ['postgres-values-boundaries-v1.json', '34c456ad0d952eedfee6cd7b7181f2b0e482b8d08e6ed877d69b7f9c3e4122b9'],
            ['postgres-local-values-boundaries-v1.json', '1b506e898a949b621e5f1978d0e2ffeea419acdbb256421811a5291fb088a6a5'],
            ['postgres-values-compound-boundaries-v1.json', 'fcce910d78706d890cf699858c1dfebbc67398a71fe17102a9a3b31a55c63560'],
            ['postgres-create-view-boundaries-v1.json', '8417042c43c0ae5fb380cc4e0bd2a3367e965f2d76b60195d044a4ff2b1346c0'],
            ['postgres-materialized-view-boundaries-v3.json', '517ae9dd87c7508cf7a982e826e7e3909a021a5ec1404d286375ae94867017e8'],
            ['postgres-create-table-as-boundaries-v1.json', '67906cdd1b60402f1d0145906b5a033ee0ae2a899b3777d80d5eed1db0235976'],
            ['postgres-table-rename-boundaries-v2.json', 'eb034cda577a7724f5e5b27f72736ca73038dea327c2cb395d7181aee37a3f68'],
            ['postgres-truncate-boundaries-v1.json', '99ebd669e0945794333bfe3ac886eefee1a2a272817e5b48cdd80f8f316d80a8'],
            ['postgres-drop-index-boundaries-v3.json', 'e718bfa6ffe7788f9cd384861e8cd629faf2f5d0030bed5cf053757b689ce838'],
            ['postgres-create-btree-boundaries-v6.json', '07fedd537da3c81d4e66f375be586f3b869fadad4463d05d89f2d66b33f711c2'],
            ['postgres-reindex-index-boundaries-v2.json', '46cbe5135cc91941064cd7187f286ce191e842a1f36759d62f289e704ec64c00'],
            ['postgres-reindex-table-boundaries-v1.json', '49fb4d1c8b77036d077aa74be41a15cdaf32b57ff464fb6ef343e88682c79021'],
            ['postgres-reindex-schema-boundaries-v1.json', 'bc883fbb2806802b00231ff895970428a0b9b5ba0df47a1c7f85171434c43a28'],
            ['postgres-reindex-partition-table-boundaries-v1.json', 'd6a322d11a35f0492afa1325d361c86c646b43041c8eeba728db9f20a2efb929'],
            ['postgres-reindex-partition-index-boundaries-v1.json', '111ef9a110275d8123cf4c085df5f56382459e789f5edc04cbf74729ad6f0987'],
            ['postgres-reindex-database-boundaries-v1.json', 'e6714016169da729bd01ed0bdd718ea5f2130638dcb81554928d1e5b1a146a01'],
            ['postgres-reindex-exclusion-index-boundaries-v1.json', 'e9b06d29c00448bc49da8da0ad6baeb2536a66aaac0a6811dbcb2d9417f330c6'],
            ['postgres-reindex-verbose-boundaries-v1.json', '11f4aa1a5303614b865eb95cfdedb7407971392ccc6a89e71e4d2e3972b8af70'],
            ['postgres-reindex-lexical-boundaries-v1.json', 'cceec322faaf51fd2a0c2c827118471150e87713e19d91190404b53e3aee7823'],
            ['postgres-index-catalog-boundaries-v1.json', 'b1e7d594943685d67bae195da774bb1b697fb6d3aa99dc78a486b2a4909dbd97'],
            ['postgres-reindex-tablespace-boundaries-v1.json', '0413d132db25a695d92486281f401e61e777acb72e33f111c893c35742b32802'],
            ['postgres-drop-schema-boundaries-v1.json', '901b88c9b2800cac32e1dbdd1b5eeb8a853e9629c07d4e155f2516d7dca88792'],
            ['postgres-create-schema-boundaries-v1.json', '2bbd48c77b335de5a3df39d59edb6b1669e056fc116148300dcee22098c446a4'],
            ['postgres-schema-tables-boundaries-v2.json', '8c0ddbbc8b0438a20197e0fdfb41ec5c08c1681b39fb47651940c2318b676975'],
            ['postgres-alter-schema-boundaries-v2.json', '226d2421cee704fb2da0f2b1c33c8f3fba46f1d71cc957d20357831a629f7c44'],
            ['postgres-create-sequence-boundaries-v1.json', '53adfda39156a8515e0ffa91d8560d90d9b2dd0c39b905f26cef0e640903ba0f'],
            ['postgres-schema-sequences-boundaries-v1.json', '69e727d2ce995052b5ac7ff221bed87c2440cd5dd420ba3d1fbb4a27882a4766'],
            ['postgres-schema-views-boundaries-v1.json', 'ea4e2123c3ffeb8d2ec9037263e5163f64d51e9844a770f0ae7ec2f2e56811be'],
            ['postgres-sequence-selectors-boundaries-v1.json', '3800a13937d55013b6eb40d44b4009cdad2243a90def3c86ab3580dbb7f66b45'],
            ['postgres-move-sequence-boundaries-v1.json', '6e8898c581ea3708f301c7c1f6830a670b9939d543f7113e6a050914d5173d8f'],
            ['postgres-reconfigure-sequence-boundaries-v1.json', '302db1bf96c4b4acc1983dca0a413837e6dd5364556360f2be3c55aadebcad9f'],
            ['postgres-sequence-logging-boundaries-v1.json', '77b7a0b4813a47d22fc33a946d28da74b0c9cadcbb3485cb7491849f251c59b1'],
            ['postgres-sequence-authorization-boundaries-v1.json', 'be8b08f9fc899c3c144ebe79792453abfd1c94b76aac9a8c034681e650c7027c'],
            ['postgres-sequence-access-boundaries-v1.json', '2381bfd00d7c950bc27b8d1b16fa49bff93c07179d05f3757df0cdd3dbd73372'],
            ['postgres-sequence-default-access-boundaries-v1.json', 'fea56a524baf43122d2fbd358051abf78777576dd9de9b22c14fb189c5a01a13'],
            ['postgres-sequence-owner-boundaries-v1.json', 'f2cf1601eef30496a6ff0a5bfc0d88a9f2291407006d45080d82fa8728cba536'],
            ['postgres-schema-indexes-boundaries-v1.json', '8d072fecb700977274cc5fd8a4e32ba153047939c6f055fff553727623b334ac'],
            ['postgres-schema-sequence-grants-boundaries-v1.json', '6c2efa8d3417b6612cdda0ea627442907164733a420024fe4215f98d3c399f71'],
            ['postgres-schema-view-dependencies-boundaries-v1.json', 'a12475fec740aded9d5c06977f2a30cb9acd2d2791e696da09a94a0d5265fae1'],
            ['postgres-schema-view-replacement-boundaries-v1.json', '8c51803bddc46c28b61f7f987378fdc885b0c56936a13224db856990c41da448'],
            ['postgres-key-constraints-boundaries-v1.json', 'f3ea6a07ebe77243e7c36968cd216850b145db5e9efbe6189b1e2e1f3be231ce'],
            ['postgres-table-width-boundaries-v1.json', 'f75aa4adb272500e54b7620c32392e6c279deb6c556cc72f15507d6b745a87b0'],
        ],
        'sqlite' => [],
    };
    foreach ($amendments as [$name, $digest]) {
        $path = __DIR__ . '/' . $name;
        requireShape(hash_file('sha256', $path) === $digest, "Amended boundary contract differs from its recorded revision: $name");
        $amendment = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $base = [$amendment['base']['path'], $amendment['base']['sha256']];
        requireShape(in_array($base, $records, true), "Amended boundary contract base is not active: $name");
        foreach ($amendment['rules'] as $rule) {
            requireShape($rule['dialects'] === [$dialect], "Amended boundary contract changed its dialect: $name");
        }
        $rules = array_replace($rules, $amendment['rules']);
    }

    return array_filter($rules, static fn (array $rule): bool => !isset($rule['dialects']) || in_array($dialect, $rule['dialects'], true));
}

function checkNativeBoundaries(
    ClassLike $class,
    string $role,
    string $platform,
    string $dialect,
    array $rules,
    Parser $parser,
    NodeTraverser $traverser,
    Standard $printer,
): array {
    $imports = '';
    foreach (['Model\\Diagnostic\\Check', 'Model\\Diagnostic\\InvalidConstruction', 'Ir\\Identifier', 'Ir\\ColumnReference', 'Ir\\Arithmetic', 'Ir\\BooleanLiteral', 'Ir\\Rows\\LocalSource', 'Ir\\Rows\\ValuesQuery', 'Ir\\Mutation\\InsertConflict', 'Ir\\From\\Join', 'Ir\\From\\DerivedSource', 'Ir\\TableSource', 'Ir\\Ordering\\Order', 'Ir\\Ordering\\ResultSql', 'Ir\\ResultColumn', 'Ir\\IntegerLiteral', 'Ir\\NullLiteral', 'Ir\\Select', 'Ir\\From\\JoinKind', 'Ir\\From\\OccurrenceScope',
        'Ir\\Mutation\\InsertRow', 'Ir\\Mutation\\UpdateAssignment', 'Ir\\Predicate\\IntegerComparison', 'Ir\\Predicate\\IntegerOperator',
        'Ir\\Predicate\\LogicalOperator', 'Ir\\Predicate\\LogicalPredicate', 'Ir\\Predicate\\NullTest', 'Ir\\SelectSql',
        'Model\\Contract\\GrammarRelease', 'Statement\\Query\\ParenthesizedQuery', 'Statement\\Relation\\DerivedTable',
        'Model\\Statement\\Reference\\Column\\ResolvedColumn', 'Ir\\SelectLowering', 'Statement\\Expression\\Collate',  'Statement\\Expression\\Grouped', 'Statement\\Query\\SelectExpression', 'Ir\\ExpressionLowering',
        'Ir\\Binding\\ParameterLowering', 'Ir\\Ordering\\ProjectionLabel'] as $name) {
        $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
    }
    if ($dialect === 'sqlite') {
        $imports .= 'use ' . $platform . '\\Ir\\From\\SelectBounds;' . "\n";
        $imports .= 'use ' . $platform . '\\Ir\\Rows\\RecursiveDefinition;' . "\n";
        $imports .= 'use ' . $platform . '\\Platform;' . "\n";
        foreach (['Ir\\SourceBinding', 'Ir\\From\\QueryClauses', 'Ir\\Mutation\\InsertConflictHandler', 'Ir\\Mutation\\InsertTargetLowering', 'Ir\\Mutation\\ReturningLowering',
            'Statement\\Mutation\\InsertDefaults', 'Statement\\Mutation\\Assignment', 'Statement\\Mutation\\ConflictResolution',
            'Ir\\Predicate\\Pattern\\PatternTest', 'Ir\\Predicate\\Pattern\\PatternKind', 'Statement\\Expression\\Operator\\PatternOperator',
            'Statement\\Expression\\Operator\\PatternMatch as ParsedPattern'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
        foreach (['Ir\\Parameter', 'Ir\\Collation', 'Ir\\Expression\\BinaryOperatorLowering', 'Ir\\ArithmeticOperator', 'Statement\\Expression\\Operator\\BinaryOperator', 'Ir\\Predicate\\ComparisonOperator', 'Statement\\Expression\\Operator\\NullTestForm', 'Ir\\Predicate\\TruthTest', 'Ir\\Predicate\\BetweenTest', 'Ir\\Predicate\\MembershipTest', 'Ir\\Predicate\\MembershipItem', 'Ir\\Predicate\\FoldedMembership', 'Ir\\Predicate\\FoldedMembershipLowering', 'Model\\Rendering\\Output', 'Model\\Rendering\\PieceKind', 'Rendering\\Codec', 'Statement\\Expression\\TruthWord',
            'Statement\\Expression\\FunctionCall', 'Statement\\Expression\\Operator\\Binary', 'Ir\\Expression\\CaseExpression', 'Ir\\Expression\\CaseBranch', 'Ir\\Expression\\CastExpression', 'Ir\\Expression\\CastTypeName', 'Model\\Contract\\TypeNameAffinity', 'Ir\\Expression\\CollatedExpression', 'Ir\\Expression\\Concatenation', 'Statement\\Expression\\Collate as ParsedCollate', 'Ir\\Expression\\CastTarget', 'Ir\\Expression\\UnaryExpression', 'Ir\\Expression\\UnaryKind',
            'Ir\\Literal\\NumericLiteralLowering', 'Statement\\Expression\\Operator\\Unary', 'Statement\\Expression\\Operator\\UnaryOperator',
            'Statement\\Expression\\Literal\\IntegerLiteral as ParsedInteger', 'Statement\\Expression\\Literal\\HexLiteral as ParsedHex',
            'Statement\\Expression\\Literal\\RealLiteral as ParsedReal', 'Statement\\Type\\Affinity', 'Ir\\Literal\\BlobLiteral',
            'Ir\\Literal\\HexIntegerLiteral', 'Ir\\Literal\\RealLiteral', 'Ir\\Literal\\TextLiteral', 'Ir\\Predicate\\Comparison', 'Ir\\Predicate\\Logical', 'Ir\\Predicate\\ExpressionScope', 'Ir\\Schema\\TableColumn',
            'Ir\\Schema\\DeclaredDatatypeName', 'Ir\\Schema\\NotNullConflict', 'Ir\\Schema\\DefaultLowering', 'Statement\\Type\\NumberSign', 'Ir\\Mutation\\ReturnedColumn', 'Ir\\Mutation\\DefaultRow',
            'Ir\\Mutation\\UpdateConflict', 'Statement\\Expression\\ColumnUse', 'Statement\\Expression\\DoubleQuotedWord', 'Statement\\Query\\TableStar', 'Ir\\From\\SourceDeclarations', 'Statement\\Schema\\Column\\NotNull',
            'Statement\\Schema\\Column\\DefaultLiteral', 'Statement\\Schema\\Column\\DefaultExpression', 'Statement\\Schema\\Column\\DefaultWord', 'Statement\\Schema\\Table\\TableOptionKind', 'Statement\\Mutation\\MutationTarget',
            'Ir\\Schema\\Alter\\RenameColumn', 'Ir\\Schema\\Alter\\RenameTable', 'Ir\\Schema\\Alter\\AlterTargetLowering', 'Ir\\Schema\\Alter\\DropColumn', 'Ir\\Schema\\Alter\\DropMember', 'Ir\\Schema\\Index\\CreateIndex', 'Ir\\Schema\\Index\\IndexTerm', 'Ir\\Schema\\Index\\DropIndexLowering',
            'Ir\\Schema\\CreateColumnLowering', 'Statement\\Schema\\AlterAddColumn', 'Model\\Statement\\Type\\Known',
            'Model\\Statement\\Declaration\\RelationKind', 'Model\\Statement\\Reference\\Table\\DeclaredTable',
            'Statement\\Schema\\AlterRenameColumn', 'Statement\\Schema\\AlterRenameTable',
            'Ir\\Schema\\CreateTable', 'Ir\\Schema\\Key\\KeyConflict', 'Ir\\Schema\\Key\\KeyTerm', 'Ir\\Schema\\Key\\PrimaryKey',
            'Ir\\Schema\\Key\\KeyTermLowering', 'Ir\\Schema\\Key\\TableKeySql', 'Ir\\Schema\\Key\\TableKeyLowering',
            'Ir\\Schema\\Key\\TableKeyLayout', 'Ir\\Schema\\Key\\UniqueKey',
            'Statement\\Schema\\LiteralColumn', 'Statement\\Query\\Ordering\\SortDirection',
            'Statement\\Schema\\Column\\ColumnPrimaryKey', 'Statement\\Schema\\Table\\TablePrimaryKey',
            'Statement\\Schema\\Column\\ColumnUnique', 'Statement\\Schema\\Table\\TableUnique',
            'Statement\\Schema\\Column\\Collation as ParsedCollation',
            'Ir\\Schema\\Predicate\\CheckConstraint', 'Ir\\Schema\\Predicate\\CheckLowering', 'Ir\\Schema\\Predicate\\ImplicitCheckLabel', 'Ir\\Schema\\Predicate\\ComparisonSpelling', 'Ir\\Schema\\Predicate\\ImplicitCheckLowering', 'Ir\\Schema\\Predicate\\DefinitionSource', 'Ir\\Schema\\TableDeclarationSql', 'Statement\\Schema\\Column\\ColumnCheck', 'Statement\\Schema\\Column\\ConstraintName', 'Statement\\Schema\\Table\\TableCheck',
            'Ir\\Schema\\Generation\\GeneratedGraph', 'Ir\\Schema\\Generation\\GeneratedLowering', 'Ir\\Schema\\Generation\\ColumnGeneration', 'Ir\\Schema\\Generation\\Storage',
            'Ir\\Rows\\LocalInputs', 'Ir\\Rows\\LocalSourceLowering', 'Ir\\From\\ExternalRelationKind',
            'Ir\\Expression\\Nulls\\Coalesce', 'Ir\\Expression\\Nulls\\CoalesceArgument', 'Ir\\Expression\\Nulls\\CoalesceLowering',
            'Statement\\Expression\\Operator\\NullTest as OperatorNullTest', 'Statement\\Expression\\Operator\\Between as ParsedBetween',
            'Statement\\Expression\\Subquery\\InList as ParsedMembership', 'Statement\\Expression\\CaseExpression as ParsedCase',
            'Statement\\Expression\\Cast as ParsedCast', 'Statement\\Expression\\Literal\\TextLiteral as ParsedText',
            'Statement\\Expression\\Literal\\BlobLiteral as ParsedBlob', 'Statement\\Expression\\Literal\\NullLiteral as ParsedNull',
            'Statement\\Expression\\BindParameter', 'Statement\\Expression\\ParameterPrefix',
            'Statement\\Relation\\JoinOn', 'Statement\\Relation\\JoinChain', 'Statement\\Relation\\JoinUsing', 'Statement\\Relation\\JoinStep',
            'Statement\\Relation\\JoinKeyword', 'Ir\\From\\JoinKeyLowering', 'Model\\Statement\\Declaration\\Column', 'Model\\Statement\\Identifier\\Name', 'Statement\\Query\\SetQuantifier', 'Statement\\Query\\With\\WithQuery',
            'Statement\\Query\\Compound', 'Model\\Statement\\Reference\\Table\\CommonTable',
            'Statement\\Relation\\TableInput', 'Model\\Statement\\Declaration\\Table',
                        'Ir\\Schema\\DropTable', 'Ir\\Schema\\DropView', 'Statement\\Schema\\AbsentRelation', 'Statement\\Schema\\SchemaObjectKind',
            'Ir\\Schema\\DeclaredDatatypeLowering', 'Statement\\Schema\\Column\\Generated', 'Statement\\Schema\\Column\\GeneratedStorage', 'Statement\\Type\\ColumnDomain',
            'Ir\\Schema\\CreateSchema', 'Ir\\Schema\\Query\\CreateTableAs', 'Ir\\Schema\\Query\\CreateAsAffinity',
            'Ir\\Schema\\Query\\CreateAsLayout', 'Ir\\Schema\\Query\\CreatedColumn', 'Ir\\Schema\\Query\\CreatedColumnDeclaration',
            'Ir\\Aggregation\\GroupedSelect', 'Ir\\Aggregation\\GroupedLowering', 'Ir\\Rows\\Compound as CompoundQuery',
            'Ir\\Rows\\CompoundLowering', 'Ir\\Rows\\CompoundBounds', 'Statement\\Query\\Compound as ParsedCompound',
            'Ir\\Schema\\View\\CompoundViewBounds', 'Ir\\Schema\\View\\ViewSql',
            'Ir\\Schema\\View\\ViewQueryBounds', 'Ir\\Schema\\View\\CreateView', 'Statement\\Schema\\CreateView as ParsedCreateView',
            'Ir\\Rows\\ValuesLowering', 'Statement\\Query\\ValuesClause',
            'Ir\\Maintenance\\FlagPragma', 'Ir\\Maintenance\\FlagName', 'Ir\\Maintenance\\FlagChange',
            'Ir\\Maintenance\\FlagArgument', 'Ir\\Maintenance\\FlagPragmaLowering',
            'Ir\\Maintenance\\PragmaLowering', 'Ir\\Maintenance\\MaintenanceLookup', 'Ir\\Maintenance\\RelationTarget',
            'Ir\\Maintenance\\PragmaScope', 'Ir\\Maintenance\\Inventory\\InventoryKind', 'Ir\\Maintenance\\Inventory\\InventoryPragma',
            'Ir\\Maintenance\\Metadata\\CatalogAliases', 'Ir\\Maintenance\\Metadata\\CatalogTable', 'Ir\\Maintenance\\Metadata\\MissingMetadataTable',
            'Ir\\Maintenance\\Metadata\\TableInspectionKind', 'Ir\\Maintenance\\Metadata\\TableInspection', 'Ir\\Maintenance\\Metadata\\TableListing',
            'Ir\\Maintenance\\Metadata\\TableMetadataLowering', 'Model\\Statement\\Identifier\\QualifiedName',
            'Ir\\Maintenance\\Metadata\\IndexInspection', 'Ir\\Maintenance\\Metadata\\IndexInspectionKind',
            'Ir\\Maintenance\\Metadata\\ForeignKeyAudit', 'Ir\\Maintenance\\Metadata\\ForeignKeyAuditLowering',
            'Ir\\Maintenance\\Integrity\\IntegrityAudit', 'Ir\\Maintenance\\Integrity\\IntegrityAuditLowering',
            'Ir\\Maintenance\\Integrity\\IntegrityKind', 'Ir\\Maintenance\\Integrity\\IntegrityLimit', 'Ir\\Maintenance\\Integrity\\IntegrityNumber',
            'Ir\\Maintenance\\Header\\HeaderPragma', 'Ir\\Maintenance\\Header\\HeaderPragmaLowering',
            'Ir\\Maintenance\\Header\\HeaderCookie', 'Ir\\Maintenance\\Header\\HeaderReadField', 'Ir\\Maintenance\\Header\\HeaderInteger',
            'Ir\\Maintenance\\Argument\\NativePragmaInteger',
            'Ir\\Maintenance\\Page\\PagePragma', 'Ir\\Maintenance\\Page\\PagePragmaKind', 'Ir\\Maintenance\\Page\\PagePragmaLowering',
            'Ir\\Maintenance\\Page\\PageLimit', 'Ir\\Maintenance\\Page\\PageLimitArgument', 'Ir\\Maintenance\\Page\\RequestedPageSize',
            'Ir\\Maintenance\\Reclaim\\AutoVacuum', 'Ir\\Maintenance\\Reclaim\\IncrementalVacuum',
            'Ir\\Maintenance\\Reclaim\\PageReclaimLimit', 'Ir\\Maintenance\\Reclaim\\ReclaimPragmaLowering', 'Ir\\Maintenance\\Reclaim\\VacuumMode',
            'Ir\\Maintenance\\Cache\\CacheAmount', 'Ir\\Maintenance\\Cache\\CacheSize', 'Ir\\Maintenance\\Cache\\CacheSpill',
            'Ir\\Maintenance\\Cache\\DefaultCachePages', 'Ir\\Maintenance\\Cache\\DefaultCacheSize', 'Ir\\Maintenance\\Cache\\SpillMode',
            'Ir\\Maintenance\\Cache\\SpillThreshold', 'Ir\\Maintenance\\Cache\\CachePragmaLowering', 'Ir\\Maintenance\\Cache\\SpillArgument',
            'Ir\\Schema\\Trigger\\CreateTrigger', 'Ir\\Schema\\Trigger\\TriggerCondition', 'Ir\\Schema\\Trigger\\TriggerDestination',
            'Ir\\Schema\\Trigger\\DropTrigger', 'Ir\\Schema\\Trigger\\Removal\\TriggerReference', 'Ir\\Schema\\Trigger\\Removal\\MissingTrigger',
            'Ir\\Schema\\Trigger\\Removal\\TriggerVerification', 'Ir\\Schema\\Trigger\\Binding\\DropTriggerLowering',
            'Ir\\Schema\\Trigger\\TriggerField', 'Ir\\Schema\\Trigger\\TriggerEvent', 'Ir\\Schema\\Trigger\\TriggerPlacement', 'Ir\\Schema\\Trigger\\TriggerTiming',
            'Ir\\Schema\\Trigger\\TriggerInsert', 'Ir\\Schema\\Trigger\\TriggerInsertField', 'Ir\\Schema\\Trigger\\TriggerUpdateName', 'Ir\\Schema\\Trigger\\RowImage',
            'Ir\\Schema\\Trigger\\TriggerInsertRow',
            'Ir\\Schema\\Trigger\\Program\\TriggerRaise', 'Ir\\Schema\\Trigger\\Program\\TriggerRaisePolicy',
            'Ir\\Schema\\Trigger\\Binding\\TriggerRaiseLowering', 'Statement\\Expression\\Raise', 'Statement\\Query\\ResultColumn as ParsedResultColumn',
            'Ir\\Schema\\Trigger\\TriggerChecks', 'Ir\\Schema\\Trigger\\TriggerSql', 'Ir\\Schema\\Trigger\\Binding\\TriggerLowering',
            'Ir\\Schema\\Trigger\\Binding\\TriggerStepLowering', 'Ir\\Schema\\Trigger\\Binding\\TriggerOperands',
            'Statement\\Trigger\\CreateTrigger as ParsedTrigger', 'Model\\Resolution\\TriggerNamespace', 'Statement\\Mutation\\InsertRows',
            'Statement\\Mutation\\Update as ParsedTriggerUpdate', 'Statement\\Mutation\\Delete as ParsedTriggerDelete',
            'Ir\\Schema\\Trigger\\Mutation\\TriggerAssignment', 'Ir\\Schema\\Trigger\\Mutation\\TriggerDelete', 'Ir\\Schema\\Trigger\\Mutation\\TriggerEquality',
            'Ir\\Schema\\Trigger\\Mutation\\TriggerTargetField', 'Ir\\Schema\\Trigger\\Mutation\\TriggerTargetNullTest', 'Ir\\Schema\\Trigger\\Mutation\\TriggerUpdate',
            'Ir\\Schema\\Trigger\\Mutation\\TriggerActionFields', 'Ir\\Schema\\Trigger\\Mutation\\TriggerActions',
            'Ir\\Schema\\Trigger\\Mutation\\TriggerMutationScope', 'Ir\\Schema\\Trigger\\Mutation\\TriggerMutationSql',
            'Ir\\Schema\\Trigger\\Binding\\TriggerMutationLowering', 'Ir\\Schema\\Trigger\\Binding\\TriggerMutationOperands',
            'Ir\\Schema\\Trigger\\Binding\\TriggerTargetLowering',
            'Ir\\Schema\\DefinitionLowering', 'Ir\\Schema\\CreateTableLowering',
            'Ir\\Schema\\Query\\CreateTableAsLowering', 'Ir\\Schema\\Index\\CreateIndexLowering', 'Ir\\Schema\\View\\CreateViewLowering',
            'Statement\\Schema\\CreateTable as ParsedCreateTable', 'Statement\\Schema\\CreateTableAs as ParsedCreateTableAs',
            'Statement\\Schema\\CreateIndex as ParsedCreateIndex',
            'Ir\\Maintenance\\Vacuum\\Vacuum', 'Ir\\Maintenance\\Vacuum\\VacuumDestination',
            'Ir\\Maintenance\\Vacuum\\VacuumRegistrations', 'Ir\\Maintenance\\Vacuum\\VacuumLowering',
            'Ir\\Expression\\Binding\\NumberedRegistrations', 'Statement\\Maintenance\\Vacuum as ParsedVacuum', 'Ir\\Rows\\ValueRow',
            'Ir\\Connection\\Attach', 'Ir\\Connection\\AttachmentName', 'Ir\\Connection\\Detach',
            'Ir\\Connection\\DetachTarget', 'Ir\\Connection\\ConnectionOperands', 'Ir\\Connection\\ConnectionLowering',
            'Statement\\Connection\\Attach as ParsedAttach', 'Statement\\Connection\\Detach as ParsedDetach', 'Statement\\Connection\\NameOperand',
            'Ir\\Maintenance\\Metadata\\IndexMetadataLowering', 'Ir\\Maintenance\\Metadata\\EmptyIndexMetadata', 'Ir\\Maintenance\\IndexTarget',
            'Statement\\Maintenance\\Pragma', 'Statement\\Maintenance\\PragmaNumber',
            'Statement\\Maintenance\\Analyze as ParsedAnalyze', 'Statement\\Maintenance\\Reindex as ParsedReindex'] as $name) {

            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }
    } else {
        $imports .= 'use ' . $platform . '\\Ir\\SelectBounds;' . "\n";
        if ($dialect === 'postgres') {
        foreach (['Ir\\Schema\\Index\\DropIndex', 'Ir\\Schema\\Index\\IndexReference', 'Ir\\Schema\\Index\\MissingIndex',
            'Ir\\Schema\\Index\\IndexDropMode', 'Ir\\Schema\\Index\\DropIndexTargetLowering', 'Ir\\Schema\\Index\\DropIndexLowering as PostgresDropIndexLowering',
            'Model\\Facade\\Index\\IndexBinding', 'Model\\Contract\\Index\\IndexSchemas', 'Model\\Contract\\Index\\StockIndexNames'] as $name) {
            $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
        }

            $imports .= 'use ' . $platform . '\\Ir\\ArithmeticOperator;' . "\n";
            foreach (['Ir\\From\\ExternalRelationKind', 'Model\\Statement\\Declaration\\RelationKind', 'Model\\Statement\\Identifier\\Comparison', 'Model\\Statement\\Reference\\Table\\DeclaredTable',
                'Ir\\Rows\\With\\WithProjection', 'Ir\\Rows\\With\\WithRead', 'Ir\\Rows\\With\\WithDefinition', 'Ir\\Rows\\With\\WithScope', 'Ir\\Rows\\With\\WithSql', 'Ir\\Rows\\With\\WithLowering', 'Ir\\Rows\\With\\WithMaterialization', 'Ir\\Rows\\LocalProjection', 'Ir\\Rows\\LocalProjectionSql', 'Ir\\Rows\\LocalProjectionLowering', 'Ir\\Rows\\LocalDefinition', 'Ir\\Rows\\LocalResultColumn', 'Statement\\Query\\With\\Materialization', 'Model\\Statement\\Reference\\Table\\CommonTable', 'Statement\\Query\\QueryExpression', 'Statement\\Query\\ExpressionTarget',
                'Ir\\Aggregation\\GroupedSelect', 'Ir\\Aggregation\\GroupedKeys', 'Ir\\Aggregation\\GroupColumn', 'Ir\\Aggregation\\AggregateBinding', 'Ir\\Aggregation\\Aggregate', 'Ir\\Aggregation\\GroupedComparison', 'Ir\\Aggregation\\HavingLowering', 'Model\\Statement\\Scalar', 'Statement\\Invocation\\FunctionCall as ParsedAggregate', 'Statement\\Query\\ExpressionTarget as Target', 'Statement\\Constraint\\Column\\DefaultExpression', 'Statement\\Constraint\\Column\\NotNull', 'Statement\\Constraint\\Column\\NullAllowed',
                'Statement\\Table\\Element\\ColumnDefinition', 'Statement\\Table\\Element\\ListedColumns', 'Statement\\Table\\Persistence',
                'Ir\\Expression\\IntegerCase', 'Ir\\Expression\\IntegerCaseArm', 'Ir\\Expression\\IntegerCaseLowering',
                'Ir\\ColumnReference as IrColumnReference', 'Ir\\From\\BooleanColumnReference', 'Ir\\From\\WideIntegerColumnReference', 'Ir\\Expression\\BooleanInput', 'Ir\\From\\ProjectionLowering', 'Model\\Statement\\Reference\\Column\\AliasTarget', 'Statement\\Query\\SortItem', 'Statement\\Query\\SortDirection', 'Statement\\Query\\NullsOrder', 'Ir\\From\\ColumnKind', 'Ir\\From\\TextColumnReference', 'Statement\\Manipulation\\TargetTable', 'Ir\\Expression\\BuiltinCast', 'Ir\\Expression\\BuiltinCastLowering', 'Ir\\Expression\\CastDestination', 'Ir\\Expression\\TextLiteral', 'Ir\\Expression\\WideIntegerLiteral', 'Ir\\Binding\\ParameterSignature', 'Ir\\From\\JoinBinding',
                'Ir\\Expression\\CollatedText', 'Ir\\Expression\\CollatedTextLowering', 'Ir\\Expression\\TextCollation', 'Statement\\Expression\\Collation',
                'Ir\\Predicate\\Text\\TextComparison', 'Ir\\Predicate\\Text\\TextComparisonLowering', 'Ir\\Predicate\\Text\\TextOperator',
                'Ir\\Predicate\\IntegerRange', 'Ir\\Predicate\\IntegerRangeLowering', 'Ir\\Predicate\\IntegerAtomLowering',
                'Ir\\Predicate\\IntegerDistinctness', 'Ir\\Predicate\\IntegerDistinctnessLowering', 'Ir\\Expression\\WideIntegerNull',
                'Ir\\Predicate\\TruthTest', 'Ir\\Predicate\\TruthOperator', 'Ir\\Predicate\\TruthTestLowering',
                'Statement\\Expression\\Predicate\\BooleanTest as ParsedTruthTest',
                'Statement\\Expression\\BinaryOperation', 'Statement\\Expression\\ColumnReference as ParsedColumn',
                'Statement\\Expression\\Operator\\Between as ParsedRange', 'Statement\\Expression\\Operator\\DistinctTest as ParsedDistinctness',
                'Statement\\Expression\\Operator\\UnaryOperation',
                'Statement\\Expression\\Constructor\\CaseExpression as ParsedCase', 'Statement\\Literal\\BooleanLiteral as ParsedBoolean',
                'Statement\\Literal\\Constant', 'Statement\\Literal\\IntegerConstant', 'Statement\\Literal\\NumericConstant', 'Statement\\Literal\\StringConstant', 'Ir\\Predicate\\PredicateLowering',
                'Ir\\From\\MergedJoinLowering', 'Statement\\Relation\\JoinKind as ParsedKind', 'Statement\\Relation\\JoinOn', 'Statement\\Relation\\JoinUsing',
                'Statement\\Relation\\RelationList', 'Statement\\Relation\\ParenthesizedJoin', 'Statement\\Relation\\TableInput', 'Model\\Statement\\Identifier\\Name', 'Ir\\SourceBinding', 'Ir\\Mutation\\ReturningSql', 'Ir\\Mutation\\ReturningLayout', 'Ir\\Mutation\\ReturningLowering',
                'Ir\\Mutation\\Insert\\Destination', 'Ir\\Mutation\\Insert\\WriteSlot', 'Ir\\Mutation\\InsertTargetBinding', 'Ir\\Mutation\\InsertCell',
                'Statement\\Name\\ObjectKind', 'Statement\\Name\\DottedName', 'Statement\\Option\\DropBehavior',
                'Ir\\Schema\\View\\DropViewLowering', 'Statement\\Query\\ValuesList',
                'Statement\\Table\\CreateTable as ParsedCreateTable', 'Statement\\Table\\View\\CreateView as ParsedCreateView', 'Statement\\Table\\View\\CreateMaterializedView as ParsedCreateMaterializedView',
                'Statement\\Table\\CreateTableAs as ParsedCreateTableAs', 'Ir\\Schema\\DefinitionQueryLowering',
                'Ir\\Schema\\Query\\CreateTableAsLowering', 'Ir\\Schema\\TableName',
                'Statement\\Object\\Reference\\RelationTarget', 'Statement\\Object\\RenamedPart',
                'Statement\\Object\\Drop as ParsedDrop', 'Statement\\Object\\Rename as ParsedRename', 'Ir\\Schema\\Alter\\RenameLowering', 'Ir\\Schema\\DropRelationLowering',
                'Statement\\Table\\TruncateTable as ParsedTruncateTable', 'Ir\\Schema\\Truncate\\TruncateLowering',
                'Ir\\Schema\\CreateTableLowering', 'Ir\\Schema\\View\\CreateViewLowering', 'Ir\\Schema\\View\\CreateMaterializedViewLowering',
                'Statement\\Option\\Toggle', 'Statement\\Option\\KeywordWord', 'Statement\\Option\\Word', 'Statement\\Table\\View\\CheckOption',
                'Statement\\Type\\Designation\\NamedDesignation', 'Statement\\Type\\TypeName',
                'Ir\\Rows\\Values\\ValuesSql', 'Ir\\Rows\\Values\\ValuesLowering',
                'Ir\\Mutation\\ReturnedColumn', 'Model\\Contract\\ParameterStyle', 'Statement\\Manipulation\\CurrentOf',
                'Statement\\Manipulation\\Assignment\\Assignment as ParsedAssignment',
                'Statement\\Expression\\ColumnReference as ParsedReturnColumn',
                'Statement\\Expression\\Cast', 'Statement\\Literal\\NullLiteral as ParsedNull', 'Statement\\Expression\\BinaryOperation as ParsedComparison',
                'Statement\\Expression\\Operator\\BooleanOperation as ParsedLogical', 'Statement\\Expression\\Operator\\BooleanOperator',
                'Statement\\Expression\\Operator\\Negation as ParsedNot', 'Statement\\Expression\\Predicate\\NullTest as ParsedNullTest',
                'Model\\Statement\\Type\\Known', 'Statement\\Type\\Descriptor\\Builtin', 'Ir\\Rows\\Compound', 'Ir\\Rows\\CompoundOperator', 'Ir\\Rows\\CompoundShape', 'Ir\\Ordering\\ResultLowering', 'Statement\\Literal\\PositionalParameter', 'Statement\\Query\\SetOperation', 'Statement\\Query\\SetQuantifier', 'Ir\\Rows\\ResultKind'] as $name) {
                $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
            }
        }
        if ($dialect === 'mysql') {
            foreach (['Ir\\ArithmeticOperator', 'Ir\\Literal\\IntegerTokenLowering', 'Ir\\Predicate\\IntegerRange',
                'Ir\\Literal\\TextLiteral', 'Ir\\Literal\\TextLiteralLowering', 'Statement\\Literal\\EscapeRule', 'Statement\\Literal\\StringLiteral',
                'Ir\\From\\ColumnKind', 'Ir\\From\\Varchar', 'Ir\\From\\TextCollation', 'Ir\\From\\TextColumnReference',
                'Ir\\From\\VarcharLowering', 'Statement\\Type\\Character', 'Statement\\Type\\Kind\\CharacterKind',
                'Ir\\Predicate\\Text\\TextComparison', 'Ir\\Predicate\\Text\\TextOperator', 'Ir\\Predicate\\Text\\TextComparisonLowering',
                'Ir\\Expression\\CollatedText', 'Ir\\Expression\\CollatedTextLowering', 'Statement\\Expression\\Operator\\Collated',
                'Ir\\Mutation\\TextUpdateAssignment', 'Ir\\Mutation\\UpdateAssignmentLowering', 'Ir\\Mutation\\Update',
                'Ir\\Mutation\\InsertCell', 'Ir\\Mutation\\Insert', 'Ir\\Mutation\\InsertTargetBinding',
                'Model\\Statement\\Type\\Nullability', 'Model\\Contract\\ParameterStyle', 'Model\\Contract\\LexicalSettings',
                'Statement\\Type\\Integral', 'Statement\\Type\\Kind\\IntegralKind',
                'Statement\\Table\\Column\\CollateAttribute', 'Statement\\Table\\Column\\DefaultLiteral', 'Statement\\Table\\Column\\KeywordAttribute',
                'Statement\\Table\\Column\\Kind\\ColumnKeyword', 'Statement\\Table\\Column\\OrdinaryColumn', 'Statement\\Table\\Column\\ColumnDefinition',
                'Statement\\Table\\Option\\EngineOption', 'Statement\\Table\\Option\\CharsetOption', 'Statement\\Table\\Option\\CollationOption',
                'Rules\\TableDefinition\\TableDeclaration', 'Rules\\SessionDatabase',
                'Ir\\Schema\\TableName',
                'Ir\\Schema\\DropTableLowering', 'Ir\\Schema\\View\\DropViewLowering',
                'Statement\\Alter\\DropTable as ParsedDropTable', 'Statement\\Alter\\RenameTable as ParsedRenameTable', 'Statement\\View\\DropView as ParsedDropView',
                'Statement\\Alter\\TruncateTable as ParsedTruncateTable', 'Ir\\Schema\\Truncate\\TruncateLowering',
                'Ir\\Schema\\Identity\\TableIdentity', 'Ir\\Schema\\Identity\\AbsentDropTarget', 'Statement\\Alter\\AbsentTable',
                'Ir\\Schema\\Like\\CreateTableLike', 'Statement\\Table\\CreateTableLike as ParsedCreateTableLike',
                'Ir\\Schema\\Query\\CreateTableAs', 'Ir\\Schema\\Query\\CreateAsLayout',
                'Ir\\Schema\\TableColumn', 'Ir\\Schema\\IntegerStorage', 'Ir\\Schema\\CreateTableLayoutLowering',
                'Ir\\From\\ExternalRelationKind', 'Model\\Statement\\Declaration\\RelationKind', 'Model\\Statement\\Reference\\Table\\DeclaredTable',
                'Model\\Statement\\Identifier\\Comparison', 'Statement\\Dml\\WriteTarget', 'Statement\\Relation\\TableReference',
                'Statement\\Query\\Direction', 'Statement\\Table\\Key\\ColumnPart', 'Statement\\Table\\Key\\IndexDefinition',
                'Statement\\Table\\Key\\IndexKind', 'Rules\\TableDefinition\\ColumnFlags',
                'Ir\\Schema\\Key\\PrimaryKey', 'Ir\\Schema\\Key\\PrimaryKeyColumn', 'Ir\\Schema\\Key\\CreatePrimaryKeyLowering', 'Ir\\Schema\\Key\\PrimaryColumnPolicy',
                'Ir\\Expression\\SignedCast', 'Ir\\Expression\\UnsignedCast', 'Ir\\Expression\\IntegralCastLowering',
                'Statement\\Literal\\Parameter as ParsedParameter',
                'Statement\\Expression\\Conversion\\Cast', 'Statement\\Type\\Kind\\CastKind',
                'Ir\\Predicate\\IntegerRangeLowering', 'Ir\\Predicate\\RangeIntegerLiteral',
                'Ir\\From\\MergedJoinLowering', 'Ir\\SourceBinding', 'Ir\\Predicate\\PredicateLowering', 'Statement\\Relation\\Join\\JoinOperator', 'Statement\\Relation\\TableList', 'Model\\Statement\\Identifier\\Name',
                'Statement\\Expression\\Operator\\Arithmetic as ParsedArithmetic', 'Statement\\Expression\\Predicate\\Between as ParsedRange',
                'Statement\\Literal\\BooleanLiteral as ParsedBoolean', 'Statement\\Literal\\NullLiteral as ParsedNull',
                'Statement\\Name\\ColumnUse', 'Statement\\Expression\\Operator\\Unary', 'Statement\\Expression\\Operator\\UnaryOperator',
                'Statement\\Literal\\NumberForm', 'Statement\\Literal\\NumberLiteral','Statement\\Literal\\SignedLiteral',
                'Statement\\Expression\\Comparison as ParsedComparison', 'Statement\\Expression\\Logical as ParsedLogical',
                'Statement\\Expression\\Not as ParsedNot', 'Statement\\Expression\\NullTest as ParsedNullTest',
                'Ir\\Rows\\With\\WithDefinition', 'Ir\\Rows\\With\\WithProjection', 'Ir\\Rows\\With\\WithRead', 'Ir\\Rows\\With\\WithScope',
                'Ir\\Rows\\With\\WithSql', 'Ir\\Rows\\With\\WithLowering', 'Ir\\Rows\\LocalProjection', 'Ir\\Rows\\LocalDefinition',
                'Ir\\Rows\\LocalProjectionLowering', 'Ir\\Rows\\LocalProjectionSql', 'Ir\\Rows\\LocalResultColumn', 'Model\\Statement\\Reference\\Table\\CommonTable',
                'Statement\\Query\\QueryExpression', 'Statement\\Query\\With\\With',
                'Ir\\Aggregation\\Aggregate', 'Ir\\Aggregation\\AggregateBinding', 'Ir\\Aggregation\\AggregateOperator', 'Ir\\Aggregation\\GroupColumn',
                'Ir\\Aggregation\\GroupedComparison', 'Ir\\Aggregation\\GroupedKeys', 'Ir\\Aggregation\\HavingLowering', 'Model\\Statement\\Reference\\Column\\AliasTarget',
                'Statement\\Call\\Aggregate\\Aggregate as ParsedAggregate', 'Statement\\Query\\SelectOption', 'Statement\\Query\\SelectExpression as Target',
                'Ir\\Schema\\View\\ViewName', 'Ir\\Schema\\View\\DropView', 'Model\\Resolution\\TableLookup', 'Model\\Statement\\Reference\\Table\\MissingTable',
                'Model\\Resolution\\TableNamespace',
                'Ir\\Schema\\View\\CreateView', 'Ir\\Schema\\View\\ViewColumn', 'Ir\\Schema\\View\\ViewAlgorithm', 'Ir\\Schema\\View\\ViewSecurity',
                'Ir\\Schema\\View\\ViewCreationPolicy', 'Ir\\Schema\\View\\ViewQueryBounds'] as $name) {
                $imports .= 'use ' . $platform . '\\' . $name . ';' . "\n";
            }
        }
        $imports .= 'use ' . $platform . '\\Ir\\Binding\\Parameter;' . "\n";
    }
    $imports .= 'use ' . $platform . '\\Model\\Statement\\Identifier\\Comparison as NameComparison;' . "\n";
    $imports .= 'use ' . $platform . '\\Statement\\Query\\Select as ParsedSelect;' . "\n";
    $imports .= 'use ' . $platform . '\\Statement\\Relation\\' . ($dialect === 'mysql' ? 'JoinedTable' : 'Join') . ' as ParsedJoin;' . "\n";
    $verified = [];
    foreach ($rules as $id => $rule) {
        [$type, $method] = explode('::', $id);
        if ($type !== $role) {
            continue;
        }
        $code = implode("\n", [...$rule['common'], ...($rule[$dialect] ?? []), ...($rule['tail'] ?? [])]);
        $parts = explode('/', $role);
        array_pop($parts);
        $namespace = implode('\\', [$platform, 'Ir', ...$parts]);
        $template = $traverser->traverse($parser->parse('<?php namespace ' . $namespace . ';' . $imports . $code));
        requireShape(count($template) === 1 && $template[0] instanceof Namespace_, "Invalid native boundary template $id");
        $statements = array_values(array_filter($template[0]->stmts, static fn ($node) => !$node instanceof Use_));
        $actual = $class->getMethod($method)?->stmts;
        requireShape($actual !== null && $printer->prettyPrint($actual) === $printer->prettyPrint($statements), "Native boundary differs in $id");
        $verified[] = $id;
    }

    return $verified;
}
