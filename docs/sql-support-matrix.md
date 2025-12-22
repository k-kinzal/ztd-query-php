# ZTD SQL Support Matrix

ZTDがサポートするSQL文の一覧です。ZTDは実データベースを変更せずにクエリ結果をシミュレートするため、すべての操作は仮想的に実行されます。

## 凡例

| ステータス | 意味 |
|-----------|------|
| Supported | サポート済み |
| Unsupported | 未サポート（挙動は設定次第） |
| Unsupported (Parser Limitation) | SQLパーサーの制限により未サポート |

---

## DML (Data Manipulation Language)

### SELECT

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `SELECT ... FROM ...` | Supported | 基本的なSELECT |
| `SELECT ... WHERE ...` | Supported | 条件付きSELECT |
| `SELECT ... JOIN ...` | Supported | JOIN付きSELECT |
| `SELECT ... GROUP BY ...` | Supported | 集約クエリ |
| `SELECT ... HAVING ...` | Supported | 集約条件 |
| `SELECT ... ORDER BY ...` | Supported | ソート付きSELECT |
| `SELECT ... LIMIT ...` | Supported | 件数制限 |
| `SELECT ... OFFSET ...` | Supported | オフセット指定 |
| `SELECT ... UNION ...` | Supported | 複数SELECTの結合 |
| `SELECT ... UNION ALL ...` | Supported | 重複許可の結合 |
| `SELECT ... EXCEPT ...` | Supported | 差集合 |
| `SELECT ... INTERSECT ...` | Supported | 積集合 |
| `SELECT ... SUBQUERY` | Supported | サブクエリ |
| `WITH ... SELECT ...` | Supported | CTE付きSELECT |
| `WITH RECURSIVE ... SELECT ...` | Supported | 再帰CTE |
| `SELECT ... WINDOW ...` | Supported | ウィンドウ関数定義 |
| `SELECT ... FOR UPDATE` | Supported | 行ロック（ZTDでは無視） |
| `SELECT ... FOR SHARE` | Supported | 共有ロック（ZTDでは無視） |
| `SELECT ... LOCK IN SHARE MODE` | Supported | 共有ロック（ZTDでは無視） |
| `SELECT ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQLパーサーが未対応 |
| `SELECT ... INTO OUTFILE ...` | Unsupported | ファイル出力は範囲外 |
| `SELECT ... INTO DUMPFILE ...` | Unsupported | ファイル出力は範囲外 |
| `SELECT ... INTO @var` | Unsupported | 変数代入は範囲外 |

### INSERT

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `INSERT INTO ... VALUES (...)` | Supported | 単一行挿入 |
| `INSERT INTO ... VALUES (...), (...)` | Supported | 複数行挿入 |
| `INSERT INTO ... SET col=val` | Supported | SET構文による挿入 |
| `WITH ... INSERT ...` | Supported | CTE付きINSERT |
| `INSERT ... SELECT ...` | Supported | サブクエリからの挿入 |
| `INSERT ... ON DUPLICATE KEY UPDATE` | Supported | UPSERT操作 |
| `INSERT IGNORE ...` | Supported | 重複時スキップ |
| `INSERT LOW_PRIORITY ...` | Supported | 優先度指定（ZTDでは無視） |
| `INSERT DELAYED ...` | Supported | 遅延挿入（ZTDでは無視） |
| `INSERT HIGH_PRIORITY ...` | Supported | 優先度指定（ZTDでは無視） |
| `INSERT ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQLパーサーが未対応 |

### REPLACE

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `REPLACE INTO ... VALUES (...)` | Supported | 置換挿入 |
| `REPLACE INTO ... SET col=val` | Supported | SET構文による置換挿入 |
| `REPLACE ... SELECT ...` | Supported | サブクエリからの置換挿入 |
| `REPLACE LOW_PRIORITY ...` | Supported | 優先度指定（ZTDでは無視） |
| `REPLACE DELAYED ...` | Supported | 遅延置換（ZTDでは無視） |

### UPDATE

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `UPDATE ... SET ... WHERE ...` | Supported | 単一テーブル更新 |
| `UPDATE ... SET ... ORDER BY ... LIMIT ...` | Supported | 件数制限付き更新 |
| `WITH ... UPDATE ...` | Supported | CTE付きUPDATE |
| `UPDATE t1, t2 SET ... WHERE ...` | Supported | 複数テーブル更新 |
| `UPDATE ... JOIN ... SET ...` | Supported | JOIN付き更新 |
| `UPDATE LOW_PRIORITY ...` | Supported | 優先度指定（ZTDでは無視） |
| `UPDATE IGNORE ...` | Supported | エラー無視 |
| `UPDATE ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQLパーサーが未対応 |

### DELETE

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `DELETE FROM ... WHERE ...` | Supported | 単一テーブル削除 |
| `DELETE FROM ... ORDER BY ... LIMIT ...` | Supported | 件数制限付き削除 |
| `DELETE ... USING ...` | Supported | USING構文による削除 |
| `DELETE t1 FROM t1 JOIN t2 ...` | Supported | JOIN削除（単一テーブル対象） |
| `WITH ... DELETE ...` | Supported | CTE付きDELETE |
| `DELETE t1, t2 FROM ...` | Supported | 複数テーブル同時削除 |
| `DELETE LOW_PRIORITY ...` | Supported | 優先度指定（ZTDでは無視） |
| `DELETE QUICK ...` | Supported | 高速削除（ZTDでは無視） |
| `DELETE IGNORE ...` | Supported | エラー無視 |
| `DELETE ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQLパーサーが未対応 |

### TRUNCATE

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `TRUNCATE TABLE ...` | Supported | シャドウストアのテーブルデータをクリア |

### CALL

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `CALL procedure_name(...)` | Unsupported | ストアドプロシージャ呼び出しは不透明なため範囲外 |

### DO

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `DO expr [, expr] ...` | Unsupported | 式実行は範囲外 |

### HANDLER

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `HANDLER ... OPEN` | Unsupported | 低レベルテーブルアクセスは範囲外 |
| `HANDLER ... READ` | Unsupported | 低レベルテーブルアクセスは範囲外 |
| `HANDLER ... CLOSE` | Unsupported | 低レベルテーブルアクセスは範囲外 |

### LOAD DATA

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `LOAD DATA INFILE ...` | Unsupported | バルクロードは複雑すぎるため範囲外 |
| `LOAD DATA LOCAL INFILE ...` | Unsupported | バルクロードは複雑すぎるため範囲外 |
| `LOAD XML ...` | Unsupported | XMLロードは範囲外 |

---

## DDL (Data Definition Language)

ZTDにおけるDDLは、実データベースのスキーマを変更せず、SchemaRegistry内の仮想スキーマを操作します。

### CREATE TABLE

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `CREATE TABLE ...` | Supported | 仮想テーブルをSchemaRegistryに登録 |
| `CREATE TABLE IF NOT EXISTS ...` | Supported | 存在しない場合のみ仮想テーブル登録 |
| `CREATE TABLE ... LIKE ...` | Supported | 既存テーブル構造のコピー |
| `CREATE TABLE ... AS SELECT ...` | Supported | SELECT結果からテーブル作成 |
| `CREATE TEMPORARY TABLE ...` | Supported | 一時テーブル作成（通常テーブルと同様に扱う） |

### CREATE (その他)

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `CREATE INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `CREATE UNIQUE INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `CREATE FULLTEXT INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `CREATE SPATIAL INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `CREATE DATABASE ...` | Unsupported | データベース作成は範囲外 |
| `CREATE SCHEMA ...` | Unsupported | データベース作成は範囲外 |
| `CREATE VIEW ...` | Unsupported | ビュー作成は範囲外 |
| `CREATE OR REPLACE VIEW ...` | Unsupported | ビュー作成は範囲外 |
| `CREATE PROCEDURE ...` | Unsupported | ストアドプロシージャは範囲外 |
| `CREATE FUNCTION ...` | Unsupported | ユーザー定義関数は範囲外 |
| `CREATE TRIGGER ...` | Unsupported | トリガーは範囲外 |
| `CREATE EVENT ...` | Unsupported | イベントスケジューラは範囲外 |
| `CREATE USER ...` | Unsupported | ユーザー管理は範囲外 |
| `CREATE ROLE ...` | Unsupported | ロール管理は範囲外 |
| `CREATE SERVER ...` | Unsupported | サーバー接続定義は範囲外 |
| `CREATE TABLESPACE ...` | Unsupported | テーブルスペース管理は範囲外 |
| `CREATE LOGFILE GROUP ...` | Unsupported | ログファイルグループは範囲外 |
| `CREATE RESOURCE GROUP ...` | Unsupported | リソースグループは範囲外 |
| `CREATE SPATIAL REFERENCE SYSTEM ...` | Unsupported | 空間参照系は範囲外 |

### ALTER TABLE

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `ALTER TABLE ... ADD COLUMN ...` | Supported | 仮想カラム追加 |
| `ALTER TABLE ... ADD [COLUMN] (col1, col2, ...)` | Supported | 複数カラム追加 |
| `ALTER TABLE ... DROP COLUMN ...` | Supported | 仮想カラム削除 |
| `ALTER TABLE ... MODIFY COLUMN ...` | Supported | 仮想カラム変更 |
| `ALTER TABLE ... CHANGE COLUMN ...` | Supported | 仮想カラム名変更 |
| `ALTER TABLE ... ALTER COLUMN ... SET DEFAULT ...` | Supported | デフォルト値変更 |
| `ALTER TABLE ... ALTER COLUMN ... DROP DEFAULT` | Supported | デフォルト値削除 |
| `ALTER TABLE ... ADD INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... ADD KEY ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... ADD FULLTEXT ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... ADD SPATIAL ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... DROP INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... DROP KEY ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... ADD PRIMARY KEY ...` | Supported | 仮想主キー追加 |
| `ALTER TABLE ... DROP PRIMARY KEY` | Supported | 仮想主キー削除 |
| `ALTER TABLE ... ADD FOREIGN KEY ...` | Supported | 仮想外部キー追加（No-op） |
| `ALTER TABLE ... DROP FOREIGN KEY ...` | Supported | 仮想外部キー削除（No-op） |
| `ALTER TABLE ... ADD CONSTRAINT ...` | Supported | 制約追加（No-op） |
| `ALTER TABLE ... DROP CONSTRAINT ...` | Supported | 制約削除（No-op） |
| `ALTER TABLE ... RENAME TO ...` | Supported | 仮想テーブル名変更 |
| `ALTER TABLE ... RENAME COLUMN ...` | Supported | カラム名変更 |
| `ALTER TABLE ... RENAME INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... RENAME KEY ...` | Unsupported | ShadowStoreで処理するため不要 |
| `ALTER TABLE ... ORDER BY ...` | Unsupported | 物理順序は範囲外 |
| `ALTER TABLE ... CONVERT TO CHARACTER SET ...` | Unsupported | 文字セット変換は範囲外 |
| `ALTER TABLE ... ENGINE = ...` | Unsupported | ストレージエンジン変更は範囲外 |
| `ALTER TABLE ... ADD PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... DROP PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... TRUNCATE PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... COALESCE PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... REORGANIZE PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... EXCHANGE PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... ANALYZE PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... CHECK PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... OPTIMIZE PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... REBUILD PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... REPAIR PARTITION ...` | Unsupported | パーティション管理は範囲外 |
| `ALTER TABLE ... REMOVE PARTITIONING` | Unsupported | パーティション管理は範囲外 |

### ALTER (その他)

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `ALTER DATABASE ...` | Unsupported | データベース変更は範囲外 |
| `ALTER SCHEMA ...` | Unsupported | データベース変更は範囲外 |
| `ALTER VIEW ...` | Unsupported | ビュー変更は範囲外 |
| `ALTER PROCEDURE ...` | Unsupported | プロシージャ変更は範囲外 |
| `ALTER FUNCTION ...` | Unsupported | 関数変更は範囲外 |
| `ALTER EVENT ...` | Unsupported | イベント変更は範囲外 |
| `ALTER USER ...` | Unsupported | ユーザー変更は範囲外 |
| `ALTER SERVER ...` | Unsupported | サーバー変更は範囲外 |
| `ALTER TABLESPACE ...` | Unsupported | テーブルスペース変更は範囲外 |
| `ALTER LOGFILE GROUP ...` | Unsupported | ログファイルグループ変更は範囲外 |
| `ALTER INSTANCE ...` | Unsupported | インスタンス変更は範囲外 |
| `ALTER RESOURCE GROUP ...` | Unsupported | リソースグループ変更は範囲外 |

### DROP

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `DROP TABLE ...` | Supported | 仮想テーブルをSchemaRegistryから削除 |
| `DROP TABLE IF EXISTS ...` | Supported | 存在時のみ仮想テーブル削除 |
| `DROP TEMPORARY TABLE ...` | Supported | 一時テーブル削除 |
| `DROP INDEX ...` | Unsupported | ShadowStoreで処理するため不要 |
| `DROP DATABASE ...` | Unsupported | データベース削除は範囲外 |
| `DROP SCHEMA ...` | Unsupported | データベース削除は範囲外 |
| `DROP VIEW ...` | Unsupported | ビュー削除は範囲外 |
| `DROP PROCEDURE ...` | Unsupported | ストアドプロシージャ削除は範囲外 |
| `DROP FUNCTION ...` | Unsupported | 関数削除は範囲外 |
| `DROP TRIGGER ...` | Unsupported | トリガー削除は範囲外 |
| `DROP EVENT ...` | Unsupported | イベント削除は範囲外 |
| `DROP USER ...` | Unsupported | ユーザー削除は範囲外 |
| `DROP ROLE ...` | Unsupported | ロール削除は範囲外 |
| `DROP SERVER ...` | Unsupported | サーバー削除は範囲外 |
| `DROP TABLESPACE ...` | Unsupported | テーブルスペース削除は範囲外 |
| `DROP LOGFILE GROUP ...` | Unsupported | ログファイルグループ削除は範囲外 |
| `DROP RESOURCE GROUP ...` | Unsupported | リソースグループ削除は範囲外 |
| `DROP SPATIAL REFERENCE SYSTEM ...` | Unsupported | 空間参照系削除は範囲外 |

### RENAME

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `RENAME TABLE ... TO ...` | Unsupported | ALTER TABLE ... RENAME TOを使用 |
| `RENAME USER ... TO ...` | Unsupported | ユーザー管理は範囲外 |

---

## TCL (Transaction Control Language)

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `START TRANSACTION` | Unsupported | 無視（No-op） |
| `START TRANSACTION WITH CONSISTENT SNAPSHOT` | Unsupported | 無視（No-op） |
| `START TRANSACTION READ ONLY` | Unsupported | 無視（No-op） |
| `START TRANSACTION READ WRITE` | Unsupported | 無視（No-op） |
| `BEGIN` | Unsupported | 無視（No-op） |
| `BEGIN WORK` | Unsupported | 無視（No-op） |
| `COMMIT` | Unsupported | 無視（No-op） |
| `COMMIT WORK` | Unsupported | 無視（No-op） |
| `COMMIT AND CHAIN` | Unsupported | 無視（No-op） |
| `COMMIT AND NO CHAIN` | Unsupported | 無視（No-op） |
| `ROLLBACK` | Unsupported | 無視（No-op） |
| `ROLLBACK WORK` | Unsupported | 無視（No-op） |
| `ROLLBACK AND CHAIN` | Unsupported | 無視（No-op） |
| `ROLLBACK AND NO CHAIN` | Unsupported | 無視（No-op） |
| `SAVEPOINT ...` | Unsupported | 無視（No-op） |
| `RELEASE SAVEPOINT ...` | Unsupported | 無視（No-op） |
| `ROLLBACK TO SAVEPOINT ...` | Unsupported | 無視（No-op） |
| `SET TRANSACTION ...` | Unsupported | 無視（No-op） |
| `SET autocommit = ...` | Unsupported | 無視（No-op） |

### XA Transactions

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `XA START ...` | Unsupported | 分散トランザクションは範囲外 |
| `XA BEGIN ...` | Unsupported | 分散トランザクションは範囲外 |
| `XA END ...` | Unsupported | 分散トランザクションは範囲外 |
| `XA PREPARE ...` | Unsupported | 分散トランザクションは範囲外 |
| `XA COMMIT ...` | Unsupported | 分散トランザクションは範囲外 |
| `XA ROLLBACK ...` | Unsupported | 分散トランザクションは範囲外 |
| `XA RECOVER ...` | Unsupported | 分散トランザクションは範囲外 |

**設計判断**: トランザクション制御は無視（No-op）とします。ZTDセッション自体が仮想的な変更管理（ShadowStore）を行うため、明示的なトランザクション制御は不要です。

---

## DCL (Data Control Language)

### 権限管理

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `GRANT ... ON ... TO ...` | Unsupported | 権限付与は範囲外 |
| `GRANT ... TO ...` | Unsupported | ロール付与は範囲外 |
| `GRANT ... WITH GRANT OPTION` | Unsupported | 権限付与は範囲外 |
| `REVOKE ... ON ... FROM ...` | Unsupported | 権限剥奪は範囲外 |
| `REVOKE ... FROM ...` | Unsupported | ロール剥奪は範囲外 |
| `REVOKE ALL PRIVILEGES ...` | Unsupported | 権限剥奪は範囲外 |

### ユーザー・ロール管理

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `CREATE USER ...` | Unsupported | ユーザー作成は範囲外 |
| `ALTER USER ...` | Unsupported | ユーザー変更は範囲外 |
| `DROP USER ...` | Unsupported | ユーザー削除は範囲外 |
| `RENAME USER ...` | Unsupported | ユーザー名変更は範囲外 |
| `CREATE ROLE ...` | Unsupported | ロール作成は範囲外 |
| `DROP ROLE ...` | Unsupported | ロール削除は範囲外 |
| `SET ROLE ...` | Unsupported | ロール有効化は範囲外 |
| `SET DEFAULT ROLE ...` | Unsupported | デフォルトロール設定は範囲外 |
| `SET PASSWORD ...` | Unsupported | パスワード設定は範囲外 |

---

## Utility Statements

### 情報取得

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `SHOW DATABASES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW SCHEMAS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW TABLES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW FULL TABLES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW TABLE STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE TABLE ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE DATABASE ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE VIEW ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE PROCEDURE ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE FUNCTION ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE TRIGGER ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE EVENT ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CREATE USER ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW COLUMNS ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW FIELDS ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW INDEX ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW KEYS ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW VARIABLES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW GLOBAL STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW GLOBAL VARIABLES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW SESSION STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW SESSION VARIABLES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW PROCESSLIST` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW FULL PROCESSLIST` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW GRANTS ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW PRIVILEGES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW ENGINES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW STORAGE ENGINES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW PLUGINS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW EVENTS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW TRIGGERS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW PROCEDURE STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW FUNCTION STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW WARNINGS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW ERRORS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW COUNT(*) WARNINGS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW COUNT(*) ERRORS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CHARACTER SET` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW CHARSET` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW COLLATION` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW BINARY LOGS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW MASTER LOGS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW MASTER STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW SLAVE STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW REPLICA STATUS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW RELAYLOG EVENTS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW BINLOG EVENTS` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW OPEN TABLES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW PROFILES` | Unsupported | メタデータクエリ（パススルー可能） |
| `SHOW PROFILE` | Unsupported | メタデータクエリ（パススルー可能） |
| `DESCRIBE ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `DESC ...` | Unsupported | メタデータクエリ（パススルー可能） |
| `EXPLAIN ...` | Unsupported | 実行計画（パススルー可能） |
| `EXPLAIN ANALYZE ...` | Unsupported | 実行計画（パススルー可能） |
| `EXPLAIN FORMAT=...` | Unsupported | 実行計画（パススルー可能） |
| `HELP ...` | Unsupported | ヘルプは範囲外 |

### セッション管理

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `USE ...` | Unsupported | データベース切り替え |
| `SET ...` | Unsupported | セッション変数設定 |
| `SET NAMES ...` | Unsupported | 文字セット設定 |
| `SET CHARACTER SET ...` | Unsupported | 文字セット設定 |
| `SET GLOBAL ...` | Unsupported | グローバル変数設定 |
| `SET SESSION ...` | Unsupported | セッション変数設定 |
| `SET @@...` | Unsupported | 変数設定 |

### テーブルメンテナンス

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `ANALYZE TABLE ...` | Unsupported | テーブル統計収集は範囲外 |
| `ANALYZE LOCAL TABLE ...` | Unsupported | テーブル統計収集は範囲外 |
| `ANALYZE NO_WRITE_TO_BINLOG TABLE ...` | Unsupported | テーブル統計収集は範囲外 |
| `CHECK TABLE ...` | Unsupported | テーブル検証は範囲外 |
| `CHECKSUM TABLE ...` | Unsupported | チェックサム検証は範囲外 |
| `OPTIMIZE TABLE ...` | Unsupported | テーブル最適化は範囲外 |
| `OPTIMIZE LOCAL TABLE ...` | Unsupported | テーブル最適化は範囲外 |
| `OPTIMIZE NO_WRITE_TO_BINLOG TABLE ...` | Unsupported | テーブル最適化は範囲外 |
| `REPAIR TABLE ...` | Unsupported | テーブル修復は範囲外 |
| `REPAIR LOCAL TABLE ...` | Unsupported | テーブル修復は範囲外 |
| `REPAIR NO_WRITE_TO_BINLOG TABLE ...` | Unsupported | テーブル修復は範囲外 |

### ロック

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `LOCK TABLES ...` | Unsupported | テーブルロックは範囲外 |
| `LOCK TABLES ... READ` | Unsupported | テーブルロックは範囲外 |
| `LOCK TABLES ... WRITE` | Unsupported | テーブルロックは範囲外 |
| `UNLOCK TABLES` | Unsupported | テーブルアンロックは範囲外 |
| `LOCK INSTANCE FOR BACKUP` | Unsupported | インスタンスロックは範囲外 |
| `UNLOCK INSTANCE` | Unsupported | インスタンスアンロックは範囲外 |
| `GET_LOCK(...)` | Unsupported | ユーザーレベルロックは範囲外 |
| `RELEASE_LOCK(...)` | Unsupported | ユーザーレベルロックは範囲外 |

### キャッシュ・フラッシュ

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `FLUSH ...` | Unsupported | キャッシュフラッシュは範囲外 |
| `FLUSH TABLES` | Unsupported | キャッシュフラッシュは範囲外 |
| `FLUSH TABLES WITH READ LOCK` | Unsupported | キャッシュフラッシュは範囲外 |
| `FLUSH PRIVILEGES` | Unsupported | 権限フラッシュは範囲外 |
| `FLUSH LOGS` | Unsupported | ログフラッシュは範囲外 |
| `FLUSH BINARY LOGS` | Unsupported | ログフラッシュは範囲外 |
| `FLUSH ENGINE LOGS` | Unsupported | ログフラッシュは範囲外 |
| `FLUSH ERROR LOGS` | Unsupported | ログフラッシュは範囲外 |
| `FLUSH GENERAL LOGS` | Unsupported | ログフラッシュは範囲外 |
| `FLUSH RELAY LOGS` | Unsupported | ログフラッシュは範囲外 |
| `FLUSH SLOW LOGS` | Unsupported | ログフラッシュは範囲外 |
| `FLUSH STATUS` | Unsupported | ステータスフラッシュは範囲外 |
| `FLUSH USER_RESOURCES` | Unsupported | リソースフラッシュは範囲外 |
| `FLUSH HOSTS` | Unsupported | ホストキャッシュは範囲外 |
| `RESET ...` | Unsupported | リセットは範囲外 |
| `RESET MASTER` | Unsupported | マスターリセットは範囲外 |
| `RESET SLAVE` | Unsupported | スレーブリセットは範囲外 |
| `RESET REPLICA` | Unsupported | レプリカリセットは範囲外 |
| `RESET QUERY CACHE` | Unsupported | クエリキャッシュリセットは範囲外 |
| `CACHE INDEX ...` | Unsupported | インデックスキャッシュは範囲外 |
| `LOAD INDEX INTO CACHE ...` | Unsupported | インデックスプリロードは範囲外 |

### サーバー管理

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `KILL ...` | Unsupported | 接続終了は範囲外 |
| `KILL CONNECTION ...` | Unsupported | 接続終了は範囲外 |
| `KILL QUERY ...` | Unsupported | クエリ終了は範囲外 |
| `SHUTDOWN` | Unsupported | サーバー停止は範囲外 |
| `RESTART` | Unsupported | サーバー再起動は範囲外 |
| `PURGE BINARY LOGS ...` | Unsupported | バイナリログ削除は範囲外 |
| `PURGE MASTER LOGS ...` | Unsupported | マスターログ削除は範囲外 |

### プラグイン・コンポーネント

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `INSTALL PLUGIN ...` | Unsupported | プラグイン管理は範囲外 |
| `UNINSTALL PLUGIN ...` | Unsupported | プラグイン管理は範囲外 |
| `INSTALL COMPONENT ...` | Unsupported | コンポーネント管理は範囲外 |
| `UNINSTALL COMPONENT ...` | Unsupported | コンポーネント管理は範囲外 |

### プリペアドステートメント

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `PREPARE ... FROM ...` | Unsupported | サーバーサイドプリペアは範囲外 |
| `EXECUTE ...` | Unsupported | サーバーサイドプリペアは範囲外 |
| `EXECUTE ... USING ...` | Unsupported | サーバーサイドプリペアは範囲外 |
| `DEALLOCATE PREPARE ...` | Unsupported | サーバーサイドプリペアは範囲外 |
| `DROP PREPARE ...` | Unsupported | サーバーサイドプリペアは範囲外 |

### レプリケーション

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `START SLAVE ...` | Unsupported | レプリケーション制御は範囲外 |
| `START REPLICA ...` | Unsupported | レプリケーション制御は範囲外 |
| `STOP SLAVE ...` | Unsupported | レプリケーション制御は範囲外 |
| `STOP REPLICA ...` | Unsupported | レプリケーション制御は範囲外 |
| `CHANGE MASTER TO ...` | Unsupported | レプリケーション設定は範囲外 |
| `CHANGE REPLICATION SOURCE TO ...` | Unsupported | レプリケーション設定は範囲外 |
| `CHANGE REPLICATION FILTER ...` | Unsupported | レプリケーション設定は範囲外 |
| `START GROUP_REPLICATION` | Unsupported | グループレプリケーションは範囲外 |
| `STOP GROUP_REPLICATION` | Unsupported | グループレプリケーションは範囲外 |
| `CLONE ...` | Unsupported | インスタンスクローンは範囲外 |

### その他

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `BINLOG ...` | Unsupported | バイナリログは範囲外 |
| `SIGNAL ...` | Unsupported | シグナル/エラー処理は範囲外 |
| `RESIGNAL ...` | Unsupported | シグナル/エラー処理は範囲外 |
| `GET DIAGNOSTICS ...` | Unsupported | 診断情報取得は範囲外 |
| `GET STACKED DIAGNOSTICS ...` | Unsupported | 診断情報取得は範囲外 |

---

## 複数ステートメント

| 構文 | ステータス | 備考 |
|-----|----------|------|
| `SELECT 1; SELECT 2` | Supported | 各ステートメントを順番に処理（`rewriteMultiple()`） |
| `INSERT ...; UPDATE ...` | Supported | 各ステートメントを順番に処理（`rewriteMultiple()`） |

**設計**: `SqlRewriter::rewriteMultiple()` メソッドで各ステートメントを順番にZTD処理します。結果は `MultiRewritePlan` として返され、各ステートメントの `RewritePlan` にアクセスできます。`nextRowset()` による結果取得もサポートします。

---

## 設計原則

1. **実DBへの影響ゼロ**: すべての操作は仮想的に実行され、実データベースは一切変更されない
2. **スキーマの仮想管理**: DDLはSchemaRegistry内で仮想スキーマとして管理される
3. **データの仮想管理**: DMLはShadowStore内で仮想データとして管理される
4. **セキュリティ優先**: 複数ステートメントの禁止、危険な操作の制限
5. **シンプルさ**: ストアドプロシージャ、トリガー、ビューなど複雑な機能は対象外

---

## 未サポートSQL時の挙動設定

未サポート（Unsupported）のSQL文が実行された場合の挙動を設定できます。

### 挙動モード

| モード | 説明 | ユースケース |
|-------|------|-------------|
| `ignore` | 何もせず無視する（デフォルト） | 本番に近い環境でのテスト |
| `notice` | 警告を出力するが続行する | 開発中のデバッグ |
| `exception` | 例外を投げる | 厳密なテスト、未対応SQLの検出 |

### 設定例

```php
use KKinzal\ZtdQueryPhp\Config\UnsupportedSqlBehavior;

$config = new ZtdConfig(
    unsupportedBehavior: UnsupportedSqlBehavior::Notice,
);
```

### 動作ルール（Behavior Rules）

`behaviorRules` を使用して、特定のSQLパターンに対して個別の動作を設定できます。
デフォルトの `unsupportedBehavior` を上書きする形で動作します。

```php
$config = new ZtdConfig(
    unsupportedBehavior: UnsupportedSqlBehavior::Exception,
    behaviorRules: [
        // プレフィックスベース（大文字小文字無視）
        'BEGIN' => UnsupportedSqlBehavior::Ignore,
        'COMMIT' => UnsupportedSqlBehavior::Ignore,
        'ROLLBACK' => UnsupportedSqlBehavior::Ignore,
        'SET autocommit' => UnsupportedSqlBehavior::Ignore,
        'SET NAMES' => UnsupportedSqlBehavior::Ignore,
    ],
);
```

### パターンマッチング

正規表現パターン（`/`で始まる文字列）も使用できます。

```php
$config = new ZtdConfig(
    unsupportedBehavior: UnsupportedSqlBehavior::Exception,
    behaviorRules: [
        '/^SET\s+SESSION/i' => UnsupportedSqlBehavior::Ignore,  // SET SESSIONは無視
        '/^SET\s+/i' => UnsupportedSqlBehavior::Notice,         // その他のSETは警告
        '/^SAVEPOINT\s+/i' => UnsupportedSqlBehavior::Ignore,   // SAVEPOINTは無視
    ],
);
```

**First-match-wins**: ルールは配列順に評価され、最初にマッチしたルールの動作が適用されます。
より具体的なパターンを先に配置してください。

### 挙動の詳細

| ステータス | ignore モード | notice モード | exception モード |
|-----------|--------------|--------------|-----------------|
| Supported | 正常実行 | 正常実行 | 正常実行 |
| Unsupported | 無視 | 警告 + 無視 | 例外（behaviorRulesで上書き可） |

### Notice出力例

```
[ZTD Notice] Unsupported SQL ignored: BEGIN
[ZTD Notice] Unsupported SQL ignored: SET NAMES utf8mb4
[ZTD Notice] Unsupported SQL ignored: CREATE DATABASE test
```

### Exception例

```php
// UnsupportedSqlBehavior::Exception モードの場合
try {
    $ztdPdo->exec('CREATE DATABASE test');
} catch (UnsupportedSqlException $e) {
    // $e->getSql() => 'CREATE DATABASE test'
    // $e->getCategory() => 'Unsupported'
}
```

### 推奨設定

| 環境 | 推奨モード | 理由 |
|-----|----------|------|
| 開発環境 | `notice` | 未対応SQLを把握しつつ開発を続行 |
| CI/テスト | `exception` + behaviorRules | 意図しない未対応SQLを検出 |
| 本番相当テスト | `ignore` | アプリの実際の挙動をテスト |
