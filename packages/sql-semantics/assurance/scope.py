"""Compare the fixed grammar scope with an independently extracted PHP inventory."""

import hashlib
import json

from pathlib import Path

TOOL = Path(__file__).resolve().parent

CONTRACTS = {
    "sqlite-pattern-v1.json": "6385d9428e2767c4e0ac9d53a22b1e4b593af2b7ccd696cbd9f4fe34c5e91d1f",
    "postgres-insert-returning-v2.json": "54420253749f6b350ac14815bab57448fe05d78ff8e1c635978ee05e9489ae2d",
    "postgres-update-returning-v4.json": "19861e0e485e9c024118dce01b45d259e431641ed253dae4e5f856b908985831",
    "postgres-update-returning-v3.json": "62d4f171e52118cb614abba4b257be5b2fc7bb699f3868d5d3ca0b0dbd341fff",
    "postgres-delete-returning-v3.json": "55f8de4b507bf8105763afa05c30d28957c874f16d825ee8e8652cd473bf26a6",
    "postgres-delete-returning-v2.json": "6e0a3beb1bd63a4d73a4172dbfce4855f4346d1401f9618638578763569cfa1c",
    "postgres-builtin-casts-v4.json": "130440f216087838a22f1a02bfc52424f04a8527b32a8dd0d90706f330e4d3d7",
    "postgres-builtin-casts-v3.json": "b439bb017cc95d4da5c419bb7e5f6229992d39a311009b1348caa5e2e5d562ad",
    "postgres-typed-parameters-v2.json": "ead84cd2f2f8292111a668ccefc0952b17815bc6ad3194cd6f889ce1ca1f5a83",
    "postgres-boolean-tests-v1.json": "db8f3a718d0eec95297d153076a3af9d8b456e4d706ebd16f10238d3a7fda32d",
    "postgres-int4-distinctness-v1.json": "7389b300bd6ee4a8eb0094bdf5ba7bf9d8aaea4bde8ee87412e725e996278cdc",
    "postgres-int4-range-v1.json": "18d4b12d531f691206f92ba36a1bf7932421581d690c72be4829289999fcb676",
    "postgres-int4-case-v1.json": "7eb2ce7eefebccb894a7b47ce725a08d381978f2ff235a7475533f074da1620b",
    "sqlite-concatenation-v1.json": "0e6cf551291823e3fa6a60bcb9ffc0624b0583533c244aa68fc27c47a24842e4",
    "sqlite-collation-v1.json": "e6095ab2d70045c9ab7f0d49ea8c06248dd2195dd0828968690a35221d45dcd6",
    "sqlite-folded-membership-v1.json": "23406667099181edb6465612e4132cb3c9b0c0cf1c58341dd1a158a8040fd084",
    "sqlite-membership-v1.json": "a4164002596a004a50c1d2d0a85fa0a4578a18980415a0dfc59784c76854dcf7",
    "sqlite-between-v1.json": "8cf61799f6bd352e6b182ce1c8e9fc1f43d2015b1c9b3b7fa835fd38920cd639",
    "mysql-signed-range-v3.json": "2c2a318ddc782dd6052e9e9935880ce64c83d7106b0a85867adf70ddd42a35be",
    "mysql-signed-casts-v2.json": "b6a092682ba55ea4ca044c0131d35612de5cb42a8d85130e2834bb8ea395c069",
    "mysql-unsigned-casts-v3.json": "f3eda2ad5418a97deeaa8920f636adc3155eaa5ed11be18d4cf808c8b556a59b",
    "mysql-signed-quotient-v1.json": "66925621bd8a6e02a6e006be1163f34622ba75af77497f1958e052b7f9097996",
    "mysql-text-literal-v1.json": "54d594a684f8196c5ed651cb5d2220a5416f8e657bcdd6def6ddb60b6fa71629",
    "mysql-varchar-slots-v2.json": "3282a89029e03bbab4448271d03cfc292eb18947d3ff0295b245290bea0eb559",
    "mysql-external-views-v1.json": "24ca8bdc80df5f8358b4020de5fad7219da5a2c58d045f4ecb0ff45cd4a8b18d",
    "mysql-drop-view-v3.json": "47bfa7dc6ab719ea2fa3feac56b0d2434c43d194ee0fce699785ebffd0f096da",
    "mysql-ordered-table-rename-v2.json": "8fd94c11049645a17e8775960a7da377d610ece5deca2bbf4b0953ed27bc1954",
    "mysql-truncate-v3.json": "dd0846981c177b548590b345ae043fc2238b28c4c7a2cdab70d707486a760d74",
    "mysql-truncate-foreign-keys-evidence-v1.json": "dcab4637dd718fb5c9dce3f7bc8dfe39f00b359287b422d25c0ba7b6424f9dd5",
    "mysql-session-drop-table-v2.json": "4fc18dcf31bb099cbb0b61b86f52c874a49f62acffe554e802ea6a41ea604efe",
    "mysql-drop-table-foreign-keys-evidence-v1.json": "ed26401b577ea646c445b84464765565340420a06480e33172c2588c1306ecb3",
    "mysql-session-create-table-v1.json": "d128967df8d5696561122f4064c976acc904293f4a301f5b8b432f3d4cdce79a",
    "mysql-innodb-declaration-width-evidence-v1.json": "d2e586ccdcc6bf395aaa6350d56504258706099749ace11d173782e8a6db4b60",
    "mysql-create-like-v1.json": "0211e157e525a76943102e25042da8a860714b6c6e75c59fb0b529aab9439070",
    "mysql-create-table-as-v1.json": "a10a0af3b78e1307374fc41a20ace5c5639659e43197a7caa61b23d1a5d9bd95",
    "mysql-create-table-as-source-v1.json": "9a04d8c983e9484e70279798569fd1d83ef5ac2ca55432fc261bfa4eb4fb0ffe",
    "mysql-create-table-as-varchar-v1.json": "a58750e80af50328a9d7f492a2e96bf9d6b49d722fd6a578026b6d4629d0d46f",
    "mysql-create-table-as-declarations-v1.json": "aaad27fe95a548871d66f8878aea697079d87add3db128aff7e64e7e1add6ebc",
    "mysql-create-table-as-width-evidence-v1.json": "597ce5dfcc3b68fe6558b2a2d2236692c4c29e8f82b867a800bfddf3783c55c7",
    "mysql-create-btree-index-evidence-v5.json": "d5e229e3abee774024da4eb9ef360b673623d70006c731159adae943230026ea",
    "mysql-drop-native-index-evidence-v4.json": "3d97452ae70caf96c47f57111ed99507304460f3260eb1a794665bc247219389",
    "mysql-native-index-policy-evidence-v4.json": "f1f7b67654b22d9b2a5219e1b60614e0c27b8fe29248950f42fb76d93a823c32",
    "mysql-alter-index-removal-evidence-v2.json": "1523c3e78f1ab6495e234d2206d64de045a810e9f3509cb57266749c43970a85",
    "mysql-index-auto-increment-evidence-v2.json": "fb03cbf71cb3d58f42fe391399d241b9845ff9a47ca22a15bd2e2346f76023c4",
    "mysql-multiple-index-removal-evidence-v2.json": "9b6ccfbbdfbc887cad5a2aabb2256eb305780e9ec0aceb558d63612de2320daf",
    "mysql-index-renames-evidence-v2.json": "c0f6530d90d23d9fbdbac1ce380006c21d06d034994d9dce10c4648c1f5881c7",
    "mysql-mixed-index-alteration-evidence-v4.json": "92cdf62f9fe2187c9da5c247b9f103df9bd9951650d389196641af09d465700a",
    "mysql-index-foreign-keys-evidence-v3.json": "9aeeb15319a4045cbf41676925778d502474406b4e15b6c50854c487a709e2a5",
    "mysql-implicit-foreign-index-evidence-v2.json": "61163af1b695b1ea0c81861c7459ac3ecd3f3661a0dcd6d71831f059b7dd8bae",
    "mysql-drop-foreign-keys-evidence-v2.json": "ffc3d82b77cdb9b1f85941437743d720cbb4208946bf3602fd0240aca30996c3",
    "mysql-foreign-key-exchange-evidence-v2.json": "92d71447548f0191a3f0b92edbe2e737a47f8cdf36175138b6cb7230130a63aa",
    "mysql-mixed-support-evidence-v2.json": "071dac466128b4396c2d3decf7a08bec47b7ffe3cacaf3839d7739e27917ceca",
    "mysql-physical-exchange-evidence-v3.json": "40ec46f7d0b9e6e1fe226b9cf33c6afd567d8e63f0374c290f0c0c23de399fa7",
    "mysql-add-foreign-keys-evidence-v3.json": "ada4f2890c01347741ae019ba709c825d20e84d0b645ca3ec165c20a8bc5f81c",
    "mysql-foreign-key-string-evidence-v1.json": "2b34e40c25a85120c62955a0fdeeb15b772bdb46c502609470ff5bc287391159",
    "mysql-foreign-key-scalar-evidence-v2.json": "14e178cbf69702558ccd1ab6753daae67c91f177d543f84f9be8d923ae104c6a",
    "mysql-foreign-key-enumeration-evidence-v2.json": "cd01a0d2fb9a7d5327aa98b63dd4e537d6e1353f42c772284413ff702732d2b9",
    "mysql-index-visibility-evidence-v2.json": "3df7302bfb4250558f066791f67b25f47196896ccd7c362b53feb8c08b1d28d7",
    "mysql-session-table-sources-v1.json": "95d14286022d773b97560ef85af6e4fd761877169fbe28e5234e80a4941e7e9d",
    "mysql-create-view-v2.json": "db587d801c66d83202d9a3841e293f2e82d47565d0fcdc230a6c9b46ec154108",
    "mysql-with-projection-v1.json": "1bcb961bb9c33ed3c8464175e9fd413adc01ad5322a4a45ef81156e145b82f58",
    "mysql-grouped-having-v2.json": "85cdba2d69bb1e59b56b4c734197cabd6dfc8434bf7b38bd88b877e35dd812a1",
    "mysql-physical-text-admission-v1.json": "1a03057e1f901d6e680ca329471a99fd7b8b169c39d19fd678cd145891a3b3e2",
    "mysql-physical-text-admission-v2.json": "2b3e592e2799f9f5a810d927c81a554eef8de52ebe370035f17947c7e6c22c25",
    "mysql-varchar-presence-v1.json": "c95df7be6ac93b7b423036791a4b967515d549f48002a342d67bcab4165c1e66",
    "mysql-varchar-effects-v1.json": "7d166c495706eaa796ae11f08233c6c23e026caa1bfd723813e94100294729a2",
    "mysql-varchar-comparison-v1.json": "5170d65217583fbe3194f02d4186445facbfab35320d302230a132599db3b9e9",
    "mysql-varchar-assignment-v4.json": "7bfd83feaa665f4619e27618f7402f43cd54c333714f37cc27bba2f4919276e2",
    "mysql-varchar-insert-v2.json": "2fd2869e86715f893955c800e4d63d60f45dd6b9b2ac42df3ab2f95cdbbddbc2",
    "mysql-varchar-insert-query-v1.json": "b7f8bffd15a3a52717baeb3491e613541f71028618dae241f19ac5ba1a8fb9b5",
    "mysql-varchar-insert-query-v2.json": "3051f65adb18ad41c371f732ede4c66f1e6cee50246e9a6455c68b9bedda2b75",
    "mysql-varchar-count-v1.json": "7c91770c3f26ffec66b122258fc64ef09231f59044a32d7c09ca7fe89ae8838b",
    "mysql-varchar-extrema-v1.json": "bb884261227a9eceaadd18e1a464aa5b64def2ae3f32ed0a013a2dee7e212a96",
    "mysql-varchar-extrema-v2.json": "a594302b5388ea5a82a95db7d02c382e674baf61ef5f70b532420a8ab621512e",
    "mysql-derived-varchar-v1.json": "e03ccc6eaff57ef5fb6b6cbd6a5e79b00676a4f1737182c33d714532e852fc25",
    "mysql-local-varchar-v1.json": "3fa99fa56fce69b09f9096ef99407ce960e24fbfcdf5e2e6b7d7a99e4268f161",
    "mysql-local-varchar-v2.json": "c856de0d9e4d1ec62f3fe93b32164154a5fa192b6eab2e3b095683ad7cf1e8d9",
    "mysql-create-varchar-v1.json": "60452105e3f68e8e35ffb7fa146a364193bc05c7c879fd1b5388a758e358d994",
    "mysql-create-signed-v2.json": "0999b5f1f5de0aff4d2d07e9d9a42ac5c53e9e165f42ad7b8d53a7cb1d7e8312",
    "mysql-create-primary-v2.json": "3e1e5737b0d8101fe7c46fcecce8396f73002aacbb8727ce2c024b628f0e9c91",
    "mysql-varchar-inheritance-v2.json": "5bd0a3474cd47e20ec070aca435175f97941a2a40c96bb40d69612687f93d28d",
    "mysql-explicit-text-collation-v1.json": "2d68bc937da34126ae9d7ec99613c713f4ce7831657af06834d539d758a22a07",
    "postgres-int4-operators-v2.json": "977d858b305d7eb4d33f3061a70781c9dc3412a4280003016b0ae92910f52bfa",
    "sqlite-truth-v2.json": "f32372203fcd2896fd116d876d7d74271f4994821c8b8a237f66006a7299685e",
    "sqlite-unary-v2.json": "56a291fac5dfef57b8616bd3c2ac4172b4790240437a59b289096fb0db9bc3a0",
    "sqlite-numeric-operators-v1.json": "e8e031e469b39e74d2a8406e850f4c37cf3a85e9ab7cd0be1b2b194cf2a2a739",
    "sqlite-insert-returning-v1.json": "178495ad0e99d5efb98330019f52d32294b3c2aa33defbb12c8d903512c60401",
    "sqlite-update-returning-v1.json": "5ef470c45a745b5348762aa4ee28499df0e2e5a1756ab2a7f7ba38e79bf36333",
    "sqlite-group-filter-v1.json": "d68d3d3b61b234694983cb65a82f405b7b700890728849a118b086fa26caf818",
    "sqlite-insert-input-v2.json": "6f2cf3ff540134b21770acd7afa69cb72ad5bf0c8a51340a48b542cea0eaa7de",
    "sqlite-cast-v1.json": "0f90c2950f97a37ccc5f8628ac5c418e7dde88ec9d4d3be8601a808dab05cedd",
    "sqlite-delete-returning-v1.json": "fc81fff184fd532c12fde78b63740676c6d40678331597a75409c95bdb6e97b0",
    "sqlite-defaults-v4.json": "07ada5b77b795821b43c0aa682803c3340112645eb1f5a61f965883534e64a49",
    "sqlite-not-null-conflict-v2.json": "7dea02740bfcba38f358358c38aa42fd54e577923a9d78beca6a425b1c59b820",
    "sqlite-coalesce-v1.json": "e5c41c106de09b1e96eb2634c14e692316704d8c60e24e3bd778d1449e30ab21",
    "sqlite-case-v1.json": "a8a4eddad1e405a41566c48439c7b521f2c787a606d90b93b8fcbbf875fa9179",
    "server-projection-admission-v1.json": "cb6cb546e11007dcd00983f2f2ed0acf1d193cb7b17676b035b6384e4e2d46dd",
    "sqlite-insert-query-v1.json": "009a9763066a4f9bd707f389f002f30dd9ca71382d5693318dbd7656ac077699",
    "wildcard-projection-v2.json": "6cb667142ac182d5e6d5b4eb950963c621cec3b4f62303ad72acb8c3ce166789",
    "server-insert-query-v3.json": "5ed3c013ca28a7dcb663fa36fe217b13eaffbc4b88cd8e4d4dd65831a48e4f00",
    "sqlite-grouped-v2.json": "2ef368050fa99f784e75c811988bc5ac3951300fd48d575193bc55a8faf74662",

    "server-grouped-v3.json": "d927ba45c6a8c77e669b6da5551e099a815d38dc63dd8338b97553d4cd0cb199",

    "server-derived-join-v1.json": "131a8055798d3395508fc269cfd895c123ac896a9f17168eedcd2b124453df1d",
    "server-derived-join-v2.json": "3d2b1652912fe098f37f7a11248f81c791762a690b799f53beac73678d1844ee",
    "server-recursive-series-v2.json": "57ec24e29d2ed8d9ff1a18cd899165303c9dc3032db1738feb61a973da7842ff",
    "server-truth-v2.json": "8ecb535370bab902634dc06671fd19fecd1bc9d1e770e501a1cb212365738f61",
    "server-local-projection-v4.json": "dbbc9157fd7d3e8ec74967bfb483346aad4dfd65df353f3bf190731c0243cfb9",
    "server-parameter-v6.json": "d3c7edac68d8e12993dccdddfaa42195c68defde2f4f778b6491b91856db6ab6",
    "server-create-table-v2.json": "b1c5f91edac3d4be6e304600ba2b480ee45497fd51e84ee90597f13a8ec6911c",
    "postgres-create-text-v1.json": "ce5f9834e38a4156fab8dde45dae1f0e2f2edc9f2f581fce03fdff0a3895fbc8",
    "postgres-create-signed-defaults-v1.json": "ab925fa9e5bd654cb8b6e9b20b4dcff3eeac415322e0398ec6dfd4087b8236a6",
    "postgres-output-order-names-v1.json": "835398078917b8a701f496ad7f14ba52f3cd7f8aac6329de08c7a4294f1d75c9",
    "postgres-source-order-v1.json": "29350c146c94bec3fbf80346f9153fb0dcf6504d8a00ea17fe20f556d91fb978",
    "postgres-signed-write-widths-v1.json": "5726305480ba26a37d4f3b59444c6e6447d0ca43eed8c4eb284292f4cbcf1e71",
    "postgres-signed-comparison-widths-v1.json": "edd544fd6c147c8188524cd2003d2bcd1bbc18cc28fd4cf382340667fb72c5f7",
    "postgres-grouped-result-order-v1.json": "ce8a7f242a23b4708cb34b6280193d0d86dd28066eee65ce3bdefd1886bc0b9d",
    "postgres-external-source-kinds-v1.json": "c37f2978ffaf44e07bae85e35df9f34cc32a853e1cdabfe5d0e7005a4f1c73c9",
    "postgres-grouped-having-v1.json": "4993af9c2d074291e62f9a17fcfdcf403e5294a1e813b7948229ff181932f012",
    "postgres-signed-range-widths-v1.json": "b38da212215669bd505e0a84aba3c7cada6f4c918c34f6d2a0cd7e4e9d5cf986",
    "postgres-with-projection-v1.json": "01663f9eb25f39934ad35ea97de0074ffdaccceae02d2c29734ac54eb297657b",
    "postgres-partial-btree-v1.json": "b0af12a3006af20f6a3f304251e134b44816c8952c3f0cada6b54ed9eeb9c876",
    "postgres-boolean-index-predicate-v1.json": "b9f964d7e2d8b0102584a0ee0322140340942beea0efe9ddf97ef168da0474e5",
    "postgres-index-truth-tests-v1.json": "7c0995045b9c80f57461da353da10591ccf776d996e6d0bfded88a519fe662b2",
    "postgres-index-boolean-values-v1.json": "51a082173a7d404983a9ea5fb251ffceeb3b4e64f56c1a3960aa88ef2e93a374",
    "postgres-reindex-index-v1.json": "961195c02b9dd75c7a3f8bc8b5a93d3922b01620ddb23ae0da219ecdc54cf237",
    "postgres-reindex-constraint-index-v1.json": "38b45ec46b0da6a420a1fb61a5ebce316a8fd4313cc14e432e7cc479d16471ae",
    "postgres-reindex-table-v3.json": "26afd147dc7107cdd66d72b39fd28a445b4b5a4236130815d555775e7998be5d",
    "postgres-reindex-schema-v2.json": "68f45a1025c1116c904b0a4adabd7675b9f8d576a72112438492d6f6a16c5577",
    "postgres-reindex-partition-table-v1.json": "4ac4a82c7f5da2cc4cd1daeb167738ad139c10d55e4f2fbed0c62257fe70b88e",
    "postgres-reindex-partition-index-v1.json": "d40f9d750809a35275ed69631b74ff9e59132c96ffbcfc178a039a8d57310bb0",
    "postgres-reindex-database-v2.json": "026c42cda0966a04adf26b3e416e3a5af9ff37538d04454d9e9c36741438e66f",
    "postgres-reindex-exclusion-index-v2.json": "edaf3da1f4463d9e8f39e63626df943e681d83f8390ee154b41a927b7207a9c7",
    "postgres-reindex-verbose-v1.json": "cdebb346ea3a7e8d2d05fd2d8577c124e2179dbfd8adb17a17fdc09ae9777675",
    "postgres-reindex-tablespace-v2.json": "979def3b0e84b55d3cb2d59b1c15db302aac721667c59c05fbc3b95e48d17754",
    "postgres-index-catalog-binding-v1.json": "c2f6d6e7a8f4e72cc90fc15daef3d6d3204538ec836ddece4c9a9453467ca128",
    "postgres-local-catalog-binding-v1.json": "855a3af17e33095b5a1603198d563d69a9b6ba373b879b716629f9a5b1ffc3ce",
    "postgres-create-btree-v2.json": "fb2bf03a058c6b1ae1e0c25f00bfacea7cbcce7cf1339767d9b63e0112d9860b",
    "postgres-automatic-btree-name-evidence-v2.json": "f29da633f4214af1bf8a0130c346926748c2f7cbe0ff4021eacf6eb6dc214603",
    "postgres-btree-native-text-evidence-v1.json": "23cf24481618b03f5b3b5159b2ea7c507fab8337fa35d9fc906748548c21c06b",
    "postgres-btree-declared-collation-evidence-v1.json": "0f82eea470f54278f3dbf35dc9a7e4a77a598e094b19efc390a9f43674003508",
    "postgres-create-collated-text-evidence-v1.json": "d0d16ec894e2767b86e3cfd5eb1cd4fc18e1ff498c8f8ad52eb1e18d0167c4ae",
    "postgres-anonymous-catalog-index-evidence-v1.json": "c63e68a988bc0e8909f9ea8d0979600b0e9471372c3004623dce92f49633e93d",
    "postgres-btree-projected-roles-v1.json": "d8f3c5c5ae9375c8e671ab7d4bd83f877a35af3781e762d121bd79d26c77bc26",
    "postgres-drop-index-v1.json": "d26bab1348dec92d608759e02d68f184ee28461aadbaf015e836f911dced5faa",
    "postgres-drop-schema-v2.json": "0cbded9faaeb487e6eb711a1f2be7a93caa5e1b1c580e612806a0c432018b58d",
    "postgres-create-schema-v4.json": "43be3791d0f01cc784f84975e844c032b8bd144c6b8fbcf6987b3e7bdb368d37",
    "postgres-schema-tables-v2.json": "daf19b6c0480964d129f12c3677bc1a546ba3da2ecf95f1daab3700cd2df50df",
    "postgres-alter-schema-v1.json": "ad8e64bdd9b855ab46749acc0575b686cc66592ee9a3eb49084d534e322198dc",
    "postgres-create-sequence-v3.json": "2456da29e44e0cb0758f2628a2ae7a783bd90752a599b54e062a43d9c96bd230",
    "postgres-sequence-ownership-v3.json": "69b0863ad0e50adf7a9139d57c2e578f7bb5e972425c1b67b6f77d69206b59ec",
    "postgres-drop-sequence-v3.json": "dcf87dc6000d6cd99acb91edb79d618d3f8b62ef15e4bb2987383ce20231c924",
    "postgres-move-sequence-v1.json": "24c7fe38fbb38791b13d6881dafb951000e415708bb53977ca17c1cb832d0328",
    "postgres-reconfigure-sequence-v5.json": "450292a076f75dbc9e455cb7894325b4f28b3adc5eace78dbf86b4b1b48584be",
    "postgres-sequence-logging-v1.json": "888fc4009a3aa9647bcc19688afedddbfdfb16a73ad9af2e83b5d5e6b6117661",
    "postgres-sequence-authorization-v1.json": "f2b80990255125fcea9ffd4bc28e0b371fff6b9fa2a611ee684068c4e3bfdbba",
    "postgres-sequence-access-v1.json": "9cb25bd56833b134ea2213137b45680a7ff9abf526687c351ad8d3ec26b70e8c",
    "postgres-sequence-default-access-v2.json": "ae7303a8273cc207a42bede9f1d71fd9020ab5fc26c54b4b990e91733b01dc7f",
    "postgres-rename-sequence-v1.json": "cbc29f97151b369e1572a4aecf2585b5fc970cf3ff41569920f5c9d3f22ef2b4",
    "postgres-schema-sequences-v3.json": "fc5e99d2e791b630c01135ac7c8344a2418df57e9a13a2be37d6f1af276eca5c",
    "postgres-schema-indexes-v2.json": "60aba853804ce6d7ec72131075382de14aa0bee42cdcf6eba170d7d8ab18ce7a",
    "postgres-schema-sequence-grants-v2.json": "f3302a9cceca6d81a18c57e687cbeb04889b9b78ca272f5abc817a049d9da3d8",
    "postgres-schema-view-dependencies-v1.json": "1351a17f257e9339f7bedf812f521caf6b51e6fb340d47721b47f6fc9bc2d81f",
    "postgres-create-primary-key-v1.json": "9b70aef7ee573717530bdacda3698adbc57c65c50afebc03a4637f99f81106be",
    "postgres-primary-key-evidence-v1.json": "4ac3f9884cfadd7e008806bc779e34cdcef27c79dd0f7d07cc1e3fdc32caf959",
    "postgres-table-width-v1.json": "20f3d5e79fa35a767394e85538396ccf8c824648d526a4f8a4ca0dec5085a975",
    "postgres-table-width-evidence-v1.json": "95dee92f0f9a3743c816c7f79c44491706ead597c460f96d03fca7348fe8e408",
    "postgres-create-unique-keys-v1.json": "e7cd49f3bfd471204d7d67729a8e6aad1097e46e9b28a8ddb7cd01604a1c2195",
    "postgres-unique-key-evidence-v2.json": "155537d5813e188ae121f5af111619db9098f27913906fa53186466f7e80e4ed",
    "postgres-schema-view-replacement-v1.json": "e56e28f03abc55470b56e00f23eeac53c3aeea757720b7676df17d6f0a4f975a",
    "postgres-schema-views-v3.json": "01a84a719c11e3d51196c3436cedbc7055620d347a55993d022806493dbc6102",
    "postgres-drop-view-v1.json": "f38844b5c9ed143cbd9b46e344f721b384c5ce9a969ec85d08e6319e95e8cd4d",
    "postgres-values-v3.json": "602fa84cd6517e5e56f7b4967669399194522ff952e36183d3acf17c08c9c3c8",
    "postgres-local-values-v2.json": "38b6cccb4b5a203d1a5e3414a924e97ba1f9977a5c8db67d396f999ed476b255",
    "postgres-values-compound-v1.json": "5593e5a6626ca8cf678d51470a767384ac649469f1823ae1251efde76ccf64e2",
    "postgres-create-view-v2.json": "4433c5665a8860047588ef378b3dcfeecb0bc0b908bdfa8dcabca410da37024d",
    "postgres-materialized-view-v1.json": "b4fa05f96b6348b486f07924beabd4208e7a4aa1fed524a8385e1b2109e47e47",
    "postgres-create-table-as-v1.json": "2b22d4e05321c7e2b50b500bc4d63b5ca57c0626558af79d44443146ead4eb9d",
    "postgres-table-rename-v2.json": "5ed9f2c09a8b9caa59b9a0fc9c9f20ffd0c1eeed02fedca55c56dd824e30b730",
    "postgres-truncate-v1.json": "3c9ef972d86bd023c234796235d58e598ddee4e54e80db2b76b9832aa58f663e",
    "postgres-int8-fields-v1.json": "233ffe316162ba2bd1d4b5e8d0e2306c8af183c0293ad4c7941d710f97e2c744",
    "postgres-boolean-write-inputs-v1.json": "0c5d1b89315cc86efc5e3ef81a33519acc56fd2cda870d6efbae56dca787d92b",
    "postgres-boolean-writes-v1.json": "da56df05098ff809b75570477e4d71d1dea17ee75b2ce6ca7202a4416b91567e",
    "postgres-boolean-returning-v1.json": "0d94e694277d6daa8921a38d41e177a314e6a0e6a92203ecaae83bf26b634e1b",
    "postgres-boolean-fields-v1.json": "be21037b2177f59687c4a02f8ef81142aa67bbc92263d2f5f6dc8e4a705b09c1",
    "postgres-create-boolean-v2.json": "5afd7c6f36188d8dad72f4ede3628804b8b0ca96f30d376d334d74f70aeab95d",
    "server-compound-v2.json": "dbbf8f67d81e09156521992de45ac350e7dffdac774200f54fc80511c8d27bf8",
    "server-drop-table-v4.json": "c3103b174dd492f3a69192ded77b43b6fe7a1eeb027145daa94cd2c29c54915c",
    "server-transaction-v1.json": "c0f7fb70d54ca3321d9104e0a239c62bdf24e48ce65febffb1906faae429dd09",
    "server-update-v2.json": "38f94fa32e0e19ddbacfba57f71f45e2e0a4ab27b12a6d84b007599a7c142542",
    "server-insert-v1.json": "0456225d41ef56fa2e8271102caae8ebb225bf9c76372eaaaa18126b2d8b0f79",
    "server-result-v2.json": "9c2b6f1012a17b4d12adc032c2a7ab89d7e66a5ee64d6648d30b1feb13fb06d6",
    "server-join-v2.json": "43bfd3ddd7271b70a61fb411e918a0a2acac55b2cd01bf5e7069baadb5f93747",
    "postgres-typed-columns-v2.json": "8454694fe46150d128c458feed1641860849c2a1a08c1ff53eaf938f714f6a7a",
    "postgres-physical-text-admission-v3.json": "347cb6995fb309333d63330164d1da64b99b568cd18b7b7f16b7bcfdc0ac6a6c",
    "postgres-physical-text-admission-v4.json": "52f64c9076735c48f875e8aa7c9b0631a98a5fbd931c00d74545cdf09bf758c5",
    "postgres-physical-text-admission-v5.json": "22250386fe0c13bb2f3fc2f406e7c2f19ec0a453899a48fafb1ad3ea481dbd44",
    "postgres-physical-text-admission-v2.json": "7a69055459387f62cd5eed479166a0555ae019ee0021a562fda10ccddc4e38db",
    "postgres-physical-text-admission-v1.json": "1904736eef3eadb830e9c4585977468eeae8d1edc14b458fdd98fc1f65a9f3cf",
    "postgres-text-insert-query-v2.json": "c96925357b9e8be0e1cac7a3a4143e660ddb0c996ee770bd08d9e7b96ef8b2ad",
    "postgres-query-write-admission-v1.json": "7d4965e16c5fdb36d2d56918bd36e5f683b18b3439dcc8e229219401b8a817ff",
    "postgres-text-insert-v2.json": "3fdc3cb62f73a95c63470c1713d6636f5e30b348ecaa370233a44646482539d5",
    "postgres-text-update-v2.json": "e94efc62c3ba1ace6febcc09d4c77f8eed8b753e23327f8ef2bba2bd545bb0b6",
    "postgres-text-returning-v2.json": "252345f6bc001056f4545abdd72a0d5def31d059bea70d29638a042e0bada831",
    "postgres-derived-text-v1.json": "642645f03a2d0bee616130899a8e909d4ff0c45263d456ca619819d1923cbda1",
    "postgres-text-declarations-v2.json": "0ecdb3eed7e3e479112c81c0fc734723632f6d0a3a0f1744877f6a8fe6477da7",
    "postgres-text-null-tests-v1.json": "44beded1705fbf79c15ebfb252cce16faf6f9d38a771787b64b5956dd615955f",
    "postgres-text-conversion-v1.json": "bf714d2c501421e925cf630bfedaf0279080bcd2d3bb7fb0537adb4375fc79e4",
    "postgres-text-literal-v1.json": "3eacae517d170d2a433eba45a7fafe5860ef6503a251a0f5cbde69a358da8e48",
    "postgres-collated-text-v2.json": "52db155a439071c10ada64947f7c34b66514ef6745d6056a135b58717c6585eb",
    "postgres-text-comparison-v2.json": "9472050feb9e35dbdd2464c07962d258ece36e17ab76fbab9a09ce38abc51668",
    "postgres-from-list-v2.json": "b6d00deef43c26922855c4386a15544fb09caced9b6ac619b2fb1b85dce1527a",
    "postgres-merged-join-v2.json": "6a42d825607c6acff928cd80fac716da6d5e3a5d1654a36ef47c145ec16c387f",
    "postgres-full-merged-join-v1.json": "faf12fc5cf9304be68cc1395a344a157fb5dc20a5f5fe04c18517058455f17ec",
    "mysql-from-list-v1.json": "840628765b42c335636c6d7eaf033a3db0b6eb63534d65351a1361ce0d7749f2",
    "mysql-merged-join-v3.json": "f6ba0df40b63e6aaf88b871fbc47b587576c1fef3af3f1a53487fd17dd440bb5",
    "server-delete-v2.json": "3a5763e35bcea1af1f7f1127dd8a157349dd8b2e79451c61e2388ec9b4780858",
    "sqlite-recursive-source-v1.json": "2c5b8873c4d6b80e6f81f3db5423539057e29117e8577bd3f887b6f43f3c52d3",
    "sqlite-compound-v1.json": "c814b6f5abcd51569cd56a6ce03743c595d513964a6191fef253c488176dc24f",
    "sqlite-scalars-v2.json": "79cf2debd501cb0f486a9d84876fd118d84fd580b60a022eeaba556d86cdf48e",
    "contract-v1.json": "7f607339a5a5424cfc1dba25823cab5cc469c20e1e01d3b314755801a6f9b849",
    "server-projection-v1.json": "4f46d75542c1cc47518a2a91c8faaf318800891281e3b06ef409d9f9236f3e97",
    "server-projection-publication-v1.json": "044933c3fbc6ee9dddf578ccfea32e06e9e45cf0c61c20bbba7b9bae03f25656",
    "sqlite-relational-v4.json": "0ff018427fb2cb7578a779d6e72330bd8cc14ac69afe9848afd1681feb144f1a",
    "sqlite-merged-join-v3.json": "691715c82cbafd5f102cd3d696822afe399d7931412902b971ac9d871a42d81b",
    "sqlite-merged-prefix-join-v1.json": "00a4e5e70f376fd54722298ee21a0d403d5b7a68d52d11f78e1d077027f166ef",
    "sqlite-merged-right-join-v2.json": "1c90e1140ed5d14681bed8162eecaf3c64e89a97256f9bf7c8f2b1b05f7654ae",
    "sqlite-merged-full-join-v1.json": "062375f0397ae9961c76709d17f5e5347a7fe3e0bebd9f65a74a93bd0e6a825d",
    "sqlite-result-v1.json": "8cd76f55067fe32c08d14b648be3a0b886b6e34785db7bb8707f20edc2c37237",
    "sqlite-delete-v1.json": "60274d9a4bf82c40f5363ead740a7465bba8bca359dfe9ead7e35f8de185cb7b",
    "sqlite-values-v1.json": "2c1f212e5fae261258dc8c0fb450fd663a41fffc965c7988346b6dcfa39c6ad3",
    "sqlite-joined-local-source-v1.json": "8fbd98e0fb1f51a16b7e0aa992967b177d2c3a502ec248d330c8b6435253a2a8",
    "sqlite-local-source-v2.json": "8ab3e4c2986b7c9eb88beab723422db103913de303c57f11c25059553d9e0ea1",
    "sqlite-mutation-conflicts-v2.json": "083319fa81636593499d85ac4420ef701acbf9cdbc275c1fa7f64a2d92d84ada",
    "sqlite-upsert-nothing-v1.json": "2247a27e0acf8b29c247fecbd880d6dd7254e92adf9f3c952fe9c00d4d873c1a",
    "sqlite-insert-v1.json": "a6ce28637ff31c61eb636d0e14170aea7c6dff83a2365c98bbdad94f8ec955da",
    "sqlite-update-v1.json": "71d081d6b35c5b38855b343617660ebb07e49f5aeb5775c6feed047208478f1d",
    "sqlite-create-view-v2.json": "1526bf5dd496d8dd120c2f590f1a56a200abba6bd7a2a17865e2a1889f82359a",
    "sqlite-create-view-grouped-v1.json": "61a910409bdbf41ecbe55b2a192a668a58434910375a7d465985c6955ffea525",
    "sqlite-create-view-compound-v1.json": "6725b2032c6ae39953af88c52a1a373688701f87e1587be15604983d6ca89288",
    "sqlite-create-view-local-v1.json": "eb0af4ed7ec9e543a9f1e45536cfcc24606abccb6629a551aba6533efad3c4a6",
    "sqlite-resolved-view-input-v2.json": "adea66f4c8d06b0eeb66d17aa0a5f1da015fde3fe49d2447e1928e36023cee78",
    "sqlite-drop-view-v1.json": "cbb70e7fe7d396931d6f1f591aa91e4e244f553df17410bce835cef96f356e73",
    "sqlite-drop-table-v2.json": "d01835110d442bdae141762032ad0904ad3174615cc6047786d42f23a8497050",
    "sqlite-strict-tables-v2.json": "0c9786f6e227573ed745d43d8b1c320b128b5b3fa4b23b3f6f40842098284302",
    "sqlite-unique-keys-v1.json": "926f08200f9d461f38153c2a342632d1fa176e13877544bc83a077dd3a99a457",
    "sqlite-folded-checks-v1.json": "cb60008a2bfcb42854ec8b7ffb17f7be4eeae896460beb054c1f54928e931741",
    "sqlite-named-checks-v4.json": "8f50053817516e759452a0da21ed563479f48a61b9c22a2a725b4cb29fa93290",
    "sqlite-generated-columns-v2.json": "726bb6dedb51a5535f4494046043e5777b9f16984fbd12146c2c75ec61c9a9fe",
    "sqlite-implicit-checks-v1.json": "94101eaf66395a96c32aca5cc6e4b79f210b0436962f1712c08e0fc14f75b766",
    "sqlite-primary-keys-v2.json": "b5ed249813c2bf4d36b971033e76bad276ad617b0cea5cc6c9cbcfc518e3a51b",
    "sqlite-auto-rowid-v1.json": "530a93ca4c873c4560f3401a65d5aeb396a2ff5275881c76c4a089e39e89d4c7",
    "sqlite-create-as-v5.json": "06b5c313bc7d216a77787303c104b7bcc9fb8cab1f494eeaaa922b330ed1a372",
    "sqlite-create-table-v3.json": "a2e7d433392ad397b62f58e5e0dc45f2307c591ad9d59c5317b49362545390c3",
    "sqlite-create-index-v6.json": "a6e90befc22a682f8c5bfb0b5c5d050f4440c1f7247ba662b02f7ca1576cfee0",
    "sqlite-index-expression-spelling-v2.json": "134cb0f660c983504545ad053bb4cfecc934cc8cf56d3e91492af20bd6a47a29",
    "sqlite-drop-index-v3.json": "50d18761f064c86d44b621cd90c8a012e9d9edc8ddbb3849ccb1921f5dfa9d78",
    "sqlite-constraint-index-names-v2.json": "207b79eb4e08203b563f2a8b2b46553bac37baea0ffc46c2946016b63de17cd8",
    "sqlite-reindex-v2.json": "a8f6e5b5455eefec2b7940c123aeddaf080afb76be02467465171866221178fc",
    "sqlite-analyze-statistics-v1.json": "732bd2ce6df5d56124baf130eb35773b13b666c9f39318802352c03056cd578a",
    "sqlite-flag-pragmas-v2.json": "68c10c1e24169885dd2ea953a119e516e889dd45d9e716392f8ce660cd60dfc0",
    "sqlite-table-metadata-v2.json": "c33090421feb4d604fac00b2961f65f9c63db9ea619e9e6a061b05feded469b8",
    "sqlite-connection-inventory-v1.json": "d1d7183b5a3f85b86723cc188e1707d9eedfefc21327d9da850470fcbf62ba89",
    "sqlite-index-metadata-v1.json": "0deb37e09f7e04f3fe46d4b19664c3289e52ba2104d4885290474403a1531da1",
    "sqlite-foreign-key-audit-v1.json": "a04f94db9a3058f7826755c4df43f54eeb21491832e93d656301dc0c8428fbe8",
    "sqlite-integrity-audit-v2.json": "ddd4deca982a5e3c1d16342d175cf20b9e692598e180f185f0b84e77490c4381",
    "sqlite-header-pragmas-v1.json": "09e3bdc05914c05d24212d3e9b88c96c4a9812c34d5257599996a6cbfa0a0d58",
    "sqlite-vacuum-v2.json": "fa051d5e85a3402c1a4db62d672a20c770fd66c3c6d02e49606b25842960cd94",
    "sqlite-connection-commands-v1.json": "1465b25a5e6233a2ad9a1fa746fe8eb6b39fe1d25e8777a94163e0409ac238bb",
    "sqlite-page-pragmas-v2.json": "c2cbc5802be0f65bca2af3b25c3ee979aba12702785b4d81920c6bf9b8114164",
    "sqlite-reclaim-pragmas-v2.json": "e182df950dd6973a4c9577379df07ba2c718d5ff0b8ca16de3aba07b5c9d78a3",
    "sqlite-cache-pragmas-v2.json": "501085d843c8c8a63660bbbfe0cf546c54d36d1bdee5a2f0a566066d2c52f5c0",
    "sqlite-audit-triggers-v2.json": "fa4707626bf562f55ad952a1c4b3d9b40df06b109e24ce2fe3eb05dc6c7a2bd1",
    "sqlite-drop-triggers-v4.json": "7784b1c111552cf523ef02e1d2f9bd6e173fff5506e97d04aa9380e19672a591",
    "sqlite-trigger-mutations-v2.json": "38eebbe8868778d844c8d18bd74a5faa45c36442f34c8e7ef82217d8b5061b56",
    "sqlite-trigger-insert-batches-v1.json": "37bb1ec003e68968ca96f1d387e36f5092d94725c36d05277f129639c6b4dde4",
    "sqlite-trigger-raise-v1.json": "24d974e9718d5d92eed53e53d76393ec2dac2e99f550924d131fa8adf40c47bb",
    "sqlite-drop-column-v4.json": "4dd63d8c4bcd8925a0a8e0caa5600b11a8618436163a7533a8ee8220fe515319",
    "sqlite-add-generated-column-v2.json": "a4cefb6be815783ddecb77b0bdde88f864e4f3a4f71911431b81233a848fef27",
    "sqlite-appended-column-checks-v3.json": "8def6d35fcfb83cd4eeb8b569894d898a8f628b2a4f2605b99cb9b109fd50c02",
    "sqlite-appended-implicit-checks-v2.json": "050bf40f741cb7799f3a8351828bf844adb58632218923384e7eb26224ee1169",
    "sqlite-computed-column-default-v3.json": "fdbe4b24fe5bb64a4a5d4a8509ff9f1ad083805e911f2e665f20c38301b1c512",
    "sqlite-add-column-v4.json": "9d19dcac2a0706782d956878b9dafb26a9e922370765ab7627031f129d65cc5b",
    "sqlite-rename-v4.json": "10291e93677e2e133e691a175dc16a2e78039dccabc6eeefef8534402f148861",
    "sqlite-transaction-v1.json": "52ba3cc00a6b7428a85d1ab7d9a55dd04cb9fcbecf0909ccc94a86e40c684afc",
}
PUBLICATION = "ae8b787caee3d25019b4723c82050b2f4ba482f13bae08bafbf800a6e75ac5b1"


def declared_constructors(dialects):
    definitions = {}
    supplemental = {}
    roots_by_dialect = {dialect: [] for dialect in dialects}
    for name, sha256 in CONTRACTS.items():
        raw = (TOOL / name).read_bytes()
        if hashlib.sha256(raw).hexdigest() != sha256:
            raise ValueError(f"Constructor contract differs from its recorded revision: {name}")
        contract = json.loads(raw)
        platforms = contract.get("dialects", ("MySql", "PostgreSql") if name == "server-projection-v1.json" else ("Sqlite",))
        for dialect in platforms:
            for role in contract.get("existingRoles", []):
                concrete = "SqlSemantics\\Platform\\" + dialect + "\\Ir\\" + role.replace("/", "\\")
                supplemental.setdefault(concrete, []).append(name)
            roots_by_dialect[dialect].extend("Ir\\" + root.replace("/", "\\") for root in contract.get("additionalPublicRoots", []))
        for role, record in contract["constructors"].items():
            concrete = record.get("type", role)
            concrete = concrete.replace("/", "\\")
            for dialect in platforms:
                clas = "SqlSemantics\\Platform\\" + dialect + "\\Ir\\" + concrete
                definitions.setdefault(clas, []).append({"record": name, "role": role})
    if not supplemental.keys() <= definitions.keys():
        raise ValueError("An evidence supplement references an unregistered constructor")
    return definitions, supplemental, roots_by_dialect


def publication_definition():
    publication = (TOOL / "publication-v94.json").read_bytes()
    if hashlib.sha256(publication).hexdigest() != PUBLICATION:
        raise ValueError("Publication contract differs from its recorded revision")
    return json.loads(publication)


def constructor_scope(by_class):
    publication = publication_definition()
    prefixes = tuple("SqlSemantics\\Platform\\" + dialect for dialect in publication["dialects"])
    routes = {publication["analyzerInterface"]: [publication["interfaceResult"]]}
    definitions, supplemental, roots_by_dialect = declared_constructors(publication["dialects"])
    for platform, dialect in zip(prefixes, publication["dialects"]):
        additions = roots_by_dialect[dialect]
        if sorted(additions) != sorted(publication["single"]["additionalDialectResults"].get(dialect, [])):
            raise ValueError(f"Publication contract does not match the recorded roots for {dialect}")
        roles = [publication["single"]["result"], *additions]
        routes[platform + "\\" + publication["single"]["method"]] = sorted(platform + "\\" + role for role in roles)
    root_types = {role for route, roles in routes.items() if route != publication["analyzerInterface"] for role in roles}
    batch_routes = {
        platform + "\\" + publication["batch"]["method"]: ["array"] for platform in prefixes
    }
    published = {
        clas for clas, record in by_class.items()
        if record["visibility"] == "public" and record["kind"] in ("Stmt_Class", "Stmt_Enum")
        and clas.startswith(tuple(prefix + "\\Ir\\" for prefix in prefixes))
    }
    publication_types = set(definitions) | {kind for cases in routes.values() for kind in cases}
    array_routes = publication["arrayRoutes"]
    for route, members in array_routes.items():
        owner, _, _ = route.partition("::")
        if owner not in definitions or not members or members[0] != "array" or set(members[1:]) - definitions.keys():
            raise ValueError(f"Array publication route has unregistered roles: {route}")
    typed_value_routes = publication["typedValueRoutes"]
    registered_inputs = set(publication["constructionInputTypes"])
    for input_type in registered_inputs:
        source = by_class.get(input_type)
        if (not source or source["visibility"] != "public" or source["kind"] != "Stmt_Class"
                or source["abstract"] or input_type in definitions):
            raise ValueError(f"Wrong or missing construction input type: {input_type}")
    for route, members in typed_value_routes.items():
        owner, _, member = route.partition("::")
        concrete = set(members) - {"null"}
        if (owner not in definitions and owner not in registered_inputs
                or not member or not concrete or len(members) != len(set(members))
                or concrete - definitions.keys()):
            raise ValueError(f"Typed publication route has unregistered roles: {route}")
    generic = publication["unconstrainedReturns"]
    generic_types = {kind.lower() for kind in generic["intrinsicKinds"]}
    non_ir_routes = {platform + "\\" + role: expected for platform in prefixes
                     for role, expected in generic["nonIrRoutes"].items()}
    expected_unconstrained = {**batch_routes, **{route: ["array"] for route in array_routes}, **non_ir_routes}
    found, unconstrained = {}, {}
    for clas, record in by_class.items():
        if record["visibility"] != "public":
            continue
        for method, returns in record["public_return_roles"].items():
            route = f"{clas}::{method}"
            if set(returns) & publication_types:
                found[route] = sorted(returns)
            if method.lower() in generic["constructionLanguageMethods"] and returns == ["untyped"]:
                continue
            signature = sorted(member.lower() if member.lower() in generic_types else member for member in returns)
            if generic_types.intersection(signature):
                unconstrained[route] = signature
                if route in array_routes and signature == ["array"]:
                    found[route] = array_routes[route]
                elif route not in non_ir_routes:
                    found[route] = signature

    routes.update(batch_routes)
    routes.update(array_routes)
    routes.update(typed_value_routes)
    unexpected_unconstrained = {
        name: unconstrained.get(name) for name in sorted(set(expected_unconstrained) | set(unconstrained))
        if expected_unconstrained.get(name) != unconstrained.get(name)
    }
    return {
        "registeredRootTypes": sorted(root_types),
        "registeredConstructors": {name: {
            "contracts": definitions[name], "supplementalEvidence": supplemental.get(name, []), "source": by_class[name]["file"],
            "sha256": by_class[name]["sha256"], "paths": by_class[name]["public_and_magic_signatures"],
            "enum": by_class[name]["enum"],
        } for name in sorted(published & set(definitions))},
        "unregisteredConstructors": sorted(published - set(definitions)),
        "missingConstructors": sorted(set(definitions) - published),
        "publicationRoutes": found,
        "unconstrainedRoutes": unconstrained,
        "unexpectedUnconstrainedRoutes": unexpected_unconstrained,
        "publicationContract": PUBLICATION,
        "unexpectedPublicationRoutes": {
            name: found.get(name) for name in sorted(set(routes) | set(found)) if routes.get(name) != found.get(name)
        },
    }


def public_scope(packages, distribution):
    required = json.loads((TOOL / "required-grammars.json").read_text())["artifacts"]
    actual = {name: sha256 for name, sha256 in distribution.items() if "/resources/productions/" in name and name.endswith(".php")}
    changed = sorted(name for name in set(required) | set(actual) if required.get(name, {}).get("sha256") != actual.get(name))
    by_class = {name: record for package in packages.values() for name, record in package["classes"].items()}

    def implements_ir(name, seen):
        if name == "SqlSemantics\\SemanticsIR":
            return True
        if name in seen or name not in by_class:
            return False
        record = by_class[name]
        return any(implements_ir(target, seen | {name}) for target in record["roles"] + record["parent_types"])

    concrete = {name: record for name, record in by_class.items() if not record["abstract"] and record["kind"] == "Stmt_Class"}
    all_roots = {name for name in concrete if implements_ir(name, set())}
    declared = constructor_scope(by_class)
    required_roots = set(declared["registeredRootTypes"])
    graph_candidates = sorted(name for name, record in by_class.items()
                              if not record["abstract"] and record["kind"] in ("Stmt_Class", "Stmt_Enum")
                              and record["visibility"] == "public" and "\\Ir\\" not in name)
    return {
        **declared,
        "requiredGrammarArtifacts": required,
        "changedGrammarArtifacts": changed,
        "completeRootTypes": sorted(all_roots),
        "unregisteredRootTypes": sorted(all_roots - required_roots),
        "missingRootTypes": sorted(required_roots - all_roots),
        "publicTypesOutsideCompletedIr": graph_candidates,
        "evidenceDomain": "dialect-owned signed-integer projections with typed parameters, binary joins with external and independent derived sources, grouped signed aggregates, filters, row selection, typed compounds, one-use local projections, single-target DELETE, literal and query INSERT, ordered UPDATE, persistent table creation and removal and basic transactions (MySQL, PostgreSQL), SQLite base-table queries, VALUES, single-target DELETE, INSERT, UPDATE, CREATE TABLE, DROP TABLE, DROP VIEW and checked table and column renames, ordinary and joined local VALUES, one recursive self source, builtin grouped counts, and transaction control",
        "openRules": "All grammar alternatives and class roles without constructor, observation and evidence records remain open.",
        "coverageClassification": "partial",
    }
