# 設計レビューへの対応

2026-09-09 の設計整合レビューと基礎アルゴリズムの追加レビューに対する修正です。
基礎の流れは `Grammar AST → 終端列 → 構造変換 → Lexeme と境界の解決 → Serializer`
を維持しています。Fuzz 判定器だけでなく、生成規則・制約付き Plan・候補選択の契約も修正しました。

| 指摘 | 実装した対応 | 主な実装・検証箇所 |
| --- | --- | --- |
| PostgreSQL の `0A000` 一括許容 | 発生元と診断を分類。文法アクションの拒否を finding とし、未対応・意味解析による未判定・受理を分離 | `fuzz/Target/PgSyntaxCheck.php`、`tests/Integration/Fuzz/` |
| 字句値が固定代表値だけ | 数値・文字列・演算子・引用形式を宣言ドメインから構成。バイト解釈は Plan コンパイル時だけ行い、値と候補を固定 | `Grammar/Generation/Value/`、`Grammar/Choice/BytePlanCompiler.php`、各方言の `ValueDefinitions` |
| DB 受理と生成各段階の永続的な測定がない | 元文法の分母を保持し、到達・出力・DB 判定を分離。字句定義・複合字句・版・変換・空白を測定し、初回 witness の入力と SQL をコーパスから独立して保存 | `Coverage/Verification/`、`Coverage/LexicalObservation.php`、`fuzz/README.md` |
| 制約付き Plan の予算不足 | 子孫・後続兄弟・出現別制約・非空条件を含む最低コストを求めてから予算を選択。完成経路の再利用は実証した上限コストと厳密な最小値を区別 | `Grammar/Derivation/ConstrainedCompletion.php`、`PlanBuilder.php`、`CompletionMemo.php` |
| 右側の候補が左側の解決を妨げる | 未処理の明示的な左境界について、空マーカーや指定済み候補を含む完成経路を確認してから確定 | `Grammar/Generation/Output/BoundaryCompletion.php` と回帰テスト |
| 大きな値の全列挙 | 値の contains/select と有限な構造候補を分離し、桁・文字・直積を構成的に選択 | `Grammar/Generation/Value/`、`IntegerLexemeGenerator.php` |
| 登録表の部分的な読み落とし | 対象の宣言領域を最後まで消費し、未対応形式で失敗。全 11 プロファイルを固定版の実ソースから再構築して照合 | `Grammar/Lexical/RegistrationTable.php`、各方言の `LexicalProfileCompiler` |
| 追跡情報の不足 | 操作ごとの削除・挿入出現、選択した版、成功時に除外された候補の理由を保持 | `TerminalSequence.php`、`ResolvedOutput.php`、`GenerationTrace.php` |
| 対応版・前提・出典の不足 | ルールの PHPDoc に固定版の参照リンクを追加。版ごとのソースと SHA-256、確認した契約、実 DB の確認範囲を公開 | [source-audit.md](source-audit.md)、[source-audit.json](source-audit.json) |

追加レビューで挙がった PostgreSQL の JSON_TABLE パス、CREATE SCHEMA、制約属性、外部キー、
トリガー・ビュー、集約引数の条件は、対象版の文法アクションに沿った構造変換へ反映しました。
値の探索を広げた後に見つかった MySQL の hostname 状態、引用識別子の末尾空白、文字セット付き
リテラル、および PostgreSQL のロール名・数値文脈なども、生成側のルールまたはドメインで扱います。
完成後の SQL を再試行・文字列修復する経路は追加していません。

## 保証と探索範囲

- 基本経路の Serializer は、解決済みの字句と境界を連結します。コメント挿入や構造判断を担当しません。
- C の scanner を自動変換しているわけではありません。キーワード登録表を機械的に取り込み、
  scanner と文法アクションの意味を手書きの宣言・変換へ移しています。出典一覧は意味の完全な同値性の証明ではありません。
- 左側の完成確認は局所的な字句境界が対象です。独自プラグインの任意の非局所依存には、明示的な構造・ドメイン規則が必要です。
- 大きな値ドメインは全列挙しませんが、有限な構造候補の選択コストは候補数に比例します。
  文字列長、文字の種類、数値の探索長には文書化した上限があり、全 scanner 表記を探索したという主張ではありません。
- 文法制約の探索には短い完成経路を試す最適化があります。経路を確認できなければ完全な有限予算探索へ進み、
  試行の打ち切りを生成不能・最小コストの証拠として使いません。
- ネイティブ判定の対象は MySQL 8.4.7、PostgreSQL 17.2、SQLite 3.47.2 です。
  他の MySQL 版はソース・プロファイル・単体テストによる確認と区別します。
- 元文法への到達率と DB 受理率は別の指標です。未対応機能や意味解析で止まった SQL を受理へ加算しません。
  PostgreSQL の raw parser と拡張プロトコル Parse も別の履歴になります。

再現手順、実際の DB 設定、プログラム全体と各 parser mode の判定方法は
[ネイティブ検証ガイド](../fuzz/README.md) を参照してください。
