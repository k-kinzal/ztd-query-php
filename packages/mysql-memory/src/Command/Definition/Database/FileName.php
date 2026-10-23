<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Database;

/**
 * Encodes identifiers displayed in legacy table-file diagnostics.
 *
 * ASCII letters, digits and underscores retain their spelling. Other characters
 * use the documented UCS-2 hexadecimal or compact letter spelling. The compact
 * map was observed through RENAME TABLE errors on MySQL 5.6.51 and 5.7.44; no
 * server implementation is used. Reserved device names receive the portable
 * suffix even on Unix. This class never accesses a database's physical files.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/identifier-mapping.html.
 *
 * @visibility MySqlMemory
 */
final class FileName
{
    /**
     * @var array<string, string>|null Compact character spellings shared by legacy releases
     */
    private static ?array $letters = null;

    /**
     * The documented compact alphabets and their fixed UCS-2 case pairs.
     * A space reserves an unused spelling; each pair's position determines its encoded bytes.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-mapping.html.
     */
    private const ALPHABETS = [
        ['0123456789', 'ghijklmnopqrstuvwxyz', 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ ØÙÚÛÜÝÞŸĀĂĄĆĈĊČĎĐĒĔĖĘĚĜĞĠĢĤĦĨĪĬĮ ĲĴĶ ĹĻĽĿŁŃŅŇ ŊŌŎŐŒŔŖŘŚŜŞŠŢŤŦŨŪŬŮŰŲŴŶŹŻŽ                        ΆΈΉΊ Α ΓΔΕΖΗ Ι ΛΜΝΞΟ Ρ  ΤΥ ΧΨΩΪΫΌΎΏ ΒΘϒϓϔΦΠ  ϚϜϞϠϢϤϦϨϪϬϮΚ Σ      ϽϾϿ            ', 'àáâãäåæçèéêëìíîïðñòóôõößøùúûüýþÿāăąćĉċčďđēĕėęěĝğġģĥħĩīĭįıĳĵķĸĺļľŀłńņňŉŋōŏőœŕŗřśŝşšţťŧũūŭůűųŵŷźżžſ                     ΐ άέήίΰαβγδεζηθικλμνξοπρςστυφχψωϊϋόύώ ϐϑ   ϕϖϗϙϛϝϟϡϣϥϧϩϫϭϯϰϱϲϳϵ϶ϸϻϼ               '],
        ['ghijklmnopqrstuvwxyz', '0123456789', 'АФЈѸҦӍӴ ՄⅠБХЉѺҨ ӶԱՅⅡВЦЊѼҪӐӸԲՆⅢГЧЋѾҬӒ ԳՇⅣДШЌҀҮӔ ԴՈⅤЕЩЍ ҰӖ ԵՉⅥЖЪЎҊҲӘ ԶՊⅦЗЫЏҌҴӚ ԷՋⅧИЬѠҎҶӜ ԸՌⅨЙЭѢҐҸӞԀԹՍⅩКЮѤҒҺӠԂԺՎⅪЛЯѦҔҼӢԄԻՏⅫМЀѨҖҾӤԆԼՐⅬНЁѪҘӀӦԈԽՑⅭОЂѬҚӁӨԊԾՒⅮПЃѮҜӃӪԌԿՓⅯРЄѰҞӅӬԎՀՔ СЅѲҠӇӮ ՁՕ ТІѴҢӉӰ Ղ  УЇѶҤӋӲ Ճ  ', 'афјѹҧӎӵ մⅰбхљѻҩ ӷայⅱвцњѽҫӑӹբնⅲгчћѿҭӓ գշⅳдшќҁүӕ դոⅴещѝ ұӗ եչⅵжъўҋҳә զպⅶзыџҍҵӛ էջⅷиьѡҏҷӝ ըռⅸйэѣґҹӟԁթսⅹкюѥғһӡԃժվⅺляѧҕҽӣԅիտⅻмѐѩҗҿӥԇլրⅼнёѫҙ ӧԉխցⅽођѭқӂөԋծւⅾпѓѯҝӄӫԍկփⅿрєѱҟӆӭԏհք сѕѳҡӈӯ ձօ тіѵңӊӱ ղ  уїѷҥӌӳ ճ  '],
        ['ghijklmnopqrstuvwxyz', 'abcdefghijklmnopqrstuvwxyz', ' ƳǞȈȰ      ḀḨṐṸṠẾỦἈἪ Ὤᾈᾬ  ƂƵǠȊȲ  Ɲ   ḂḪṒṺ ỀỨἉἫ Ὥᾉᾭ  ƄƸǢȌ       ḄḬṔṼ ỂỪἊἬ Ὦᾊᾮ  Ƈ ǤȎ  Ɠ Ʈ  ḆḮṖṾ ỄỬἋἭ Ὧᾋᾯ  Ƌ ǦȐ   Ɵ   ḈḰṘẀ ỆỮἌἮ ᾺᾌᾸ    ǨȒ    Ʊ  ḊḲṚẂẠỈỰἍἯὙΆᾍᾹ  Ƒ ǪȔ  Ɣ Ʋ  ḌḴṜẄẢỊỲἎἸ Ὲᾎ   ǶƼǬȖ       ḎḶṞẆẤỌỴἏἹὛΈᾏᾼῨ ƘǄǮȘȺ      ḐḸ ẈẦỎỶἘἺ Ὴᾘ Ῡ ȽǇ ȚȻ      ḒḺṢẊẨỐỸἙἻὝΉᾙ    ǊǱȜȾƁ     ḔḼṤẌẪỒ ἚἼ Ὶᾚ   ȠǍǴȞ ƆƗ    ḖḾṦẎẬỔ ἛἽὟΊᾛ   ƠǏǸ   Ɩ    ḘṀṨẐẮỖ ἜἾ Ὸᾜ Ῥ ƢǑǺȢ Ɖ  Ʒ  ḚṂṪẒẰỘ ἝἿ Όᾝῌ  ƤǓǼȤ Ɗ     ḜṄṬẔẲỚ  Ὀ Ὺᾞ   ƧǕǾȦ   ƦɁ  ḞṆṮ ẴỜ  Ὁ Ύᾟ    ǗȀȨ Ə     ḠṈṰ ẶỞ  ὊὨῺᾨ    ǙȂȪ       ḢṊṲ ẸỠ  ὋὩΏᾩ   ƬǛȄȬ ƐƜƩ   ḤṌṴ ẺỢ ἨὌὪ ᾪῘῼ ƯƎȆȮ       ḦṎṶ ẼỤ ἩὍὫ ᾫῙ  ', 'ƀƴǟȉȱ ɝɱʅʙʭḁḩṑṹẛếủἀἢ ὤᾀᾤῒ ƃƶǡȋȳ ɞɲʆʚʮḃḫṓṻ ềứἁἣ ὥᾁᾥΐῶƅƹǣȍȴ ɟɳʇʛʯḅḭṕṽ ểừἂἤ ὦᾂᾦ ῷƈƺǥȏȵ ɠɴʈʜ ḇḯṗṿ ễửἃἥ ὧᾃᾧ  ƌƻǧȑȶ ɡɵʉʝ ḉḱṙẁ ệữἄἦὐὰᾄᾰῖ ƍ ǩȓȷ ɢɶʊʞ ḋḳṛẃạỉựἅἧὑάᾅᾱῗ ƒƾǫȕȸ ɣɷʋʟ ḍḵṝẅảịỳἆἰὒὲᾆᾲ  ƕƽǭȗȹɐɤɸʌʠ ḏḷṟẇấọỵἇἱὓέᾇᾳῠ ƙǆǯș ɑɥɹʍʡ ḑḹṡẉầỏỷἐἲὔὴᾐᾴῡ ƚǉǰțȼɒɦɺʎʢ ḓḻṣẋẩốỹἑἳὕήᾑ ῢ ƛǌǳȝ ɓɧɻʏʣ ḕḽṥẍẫồ ἒἴὖὶᾒᾶΰ ƞǎǵȟȿɔɨɼʐʤ ḗḿṧẏậổ ἓἵὗίᾓᾷῤ ơǐǹȡɀɕɩɽʑʥ ḙṁṩẑắỗ ἔἶ ὸᾔῂῥ ƣǒǻȣ ɖɪɾʒʦ ḛṃṫẓằộ ἕἷ όᾕῃῦ ƥǔǽȥ ɗɫɿʓʧ ḝṅṭẕẳớ  ὀ ὺᾖῄῧ ƨǖǿȧ ɘɬʀʔʨ ḟṇṯẖẵờ  ὁ ύᾗ   ƪǘȁȩ əɭʁʕʩ ḡṉṱẗặở  ὂὠὼᾠῆ  ƫǚȃȫ ɚɮʂʖʪ ḣṋṳẘẹỡ  ὃὡώᾡῇῲ ƭǜȅȭ ɛɯʃʗʫ ḥṍṵẙẻợ ἠὄὢ ᾢῐῳ ưǝȇȯ ɜɰʄʘʬ ḧṏṷẚẽụ ἡὅὣ ᾣῑ  '],
        ['@', 'abcdefghijklmnopqrstuvwxyz', 'ⒶⒷⒸⒹⒺⒻⒼⒽⒾⒿⓀⓁⓂⓃⓄⓅⓆⓇⓈⓉⓊⓋⓌⓍⓎⓏ', 'ⓐⓑⓒⓓⓔⓕⓖⓗⓘⓙⓚⓛⓜⓝⓞⓟⓠⓡⓢⓣⓤⓥⓦⓧⓨⓩ'],
        ['abcdefghijklmnopqrstuvwxyz', '@', 'ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺ', 'ａｂｃｄｅｆｇｈｉｊｋｌｍｎｏｐｑｒｓｔｕｖｗｘｙｚ'],
    ];

    /**
     * Answers the filename spelling of one database or table identifier.
     */
    public function encoded(string $identifier): string
    {
        if (str_starts_with($identifier, '#mysql50#') && strlen($identifier) > 9) {
            $raw = substr($identifier, 9);
            return strpbrk($raw, './\\') === false ? $raw : '';
        }
        $letters = self::$letters ??= $this->letters();
        $name = '';
        foreach (mb_str_split($identifier, 1, 'UTF-8') as $character) {
            $point = mb_ord($character, 'UTF-8');
            $name .= preg_match('/\A[A-Za-z0-9_]\z/', $character) === 1 ? $character : ($letters[$character] ?? sprintf('@%04x', $point));
        }
        return preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])\z/i', $identifier) === 1 ? $name . '@@@' : $name;
    }

    /**
     * Builds the compact spellings from the documented byte patterns and fixed case pairs.
     *
     * @return array<string, string>
     */
    public function letters(): array
    {
        $letters = [];
        foreach (self::ALPHABETS as [$first, $second, $upper, $lower]) {
            $capitals = mb_str_split($upper, 1, 'UTF-8');
            $small = mb_str_split($lower, 1, 'UTF-8');
            $position = 0;
            foreach (str_split($first) as $left) {
                foreach (str_split($second) as $right) {
                    if ($small[$position] !== ' ') {
                        $letters[$small[$position]] = '@' . $left . $right;
                    }
                    if ($capitals[$position] !== ' ') {
                        $letters[$capitals[$position]] = '@' . (ctype_alpha($left) ? strtoupper($left) . $right : $left . strtoupper($right));
                    }
                    ++$position;
                }
            }
        }
        return $letters;
    }
}
