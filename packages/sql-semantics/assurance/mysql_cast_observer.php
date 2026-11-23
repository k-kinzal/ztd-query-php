<?php

declare(strict_types=1);

// Read native result metadata on the isolated fixture connection.
if (($argv[1] ?? null) !== 'mysql' || PHP_INT_SIZE !== 8) {
    throw new RuntimeException('The signed cast observer needs a 64-bit MySQL fixture host.');
}
$dsn = getenv('ZTD_IR_MYSQL_DSN');
$user = getenv('ZTD_IR_MYSQL_USER');
$password = getenv('ZTD_IR_MYSQL_PASSWORD');
if ($dsn === false || $user === false || $password === false
    || preg_match('/\Amysql:host=127\.0\.0\.1;port=([1-9][0-9]{0,4});dbname=ir_fixture;charset=utf8mb4\z/', $dsn, $matches) !== 1) {
    throw new RuntimeException('The signed cast observer needs the local disposable fixture connection.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', $user, $password, 'ir_fixture', (int) $matches[1]);
$db->set_charset('utf8mb4');
$db->query("SET SESSION sql_mode = '', time_zone = '+00:00'");
$query = $db->prepare('SELECT VERSION(), @@lower_case_table_names, @@sql_mode, @@character_set_connection, @@character_set_results');
$query->execute();
$profile = $query->get_result()->fetch_row();
$query->close();
echo json_encode(['database' => $profile, 'php' => PHP_VERSION, 'client' => mysqli_get_client_info()], JSON_THROW_ON_ERROR) . "\n";

function measureSignedCast(mysqli $db, array $input, bool &$reading): array
{
    if (isset($input['fixture'])) {
        if ($reading) {
            $db->rollback();
            $reading = false;
        }
        foreach ($input['fixture'] as $sql) {
            $db->query($sql);
        }
        return ['status' => 'fixture'];
    }
    if (!$reading) {
        $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $reading = true;
    }
    try {
        $statement = $db->prepare($input['sql']);
        $statement->execute();
        $result = $statement->get_result();
        if (!$result instanceof mysqli_result) {
            throw new RuntimeException('A cast projection must provide native field metadata.');
        }
        $fields = [];
        foreach ($result->fetch_fields() as $field) {
            $fields[] = ($input['observeProtocolCollations'] ?? false) === true
                ? [$field->name, $field->type, $field->charsetnr]
                : [$field->name, $field->type, ($field->flags & MYSQLI_UNSIGNED_FLAG) !== 0];
        }
        $rows = $result->fetch_all(MYSQLI_NUM);
        $result->free();
        $statement->close();

        return ['status' => 'rows', 'fields' => $fields, 'rows' => $rows];
    } catch (mysqli_sql_exception $error) {
        $db->rollback();
        $reading = false;

        return ['status' => 'db-error', 'state' => $error->getSqlState()];
    }
}

$reading = false;
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (array_is_list($input)) {
        $response = [];
        foreach ($input as $request) {
            $response[] = measureSignedCast($db, $request, $reading);
        }
    } else {
        $response = measureSignedCast($db, $input, $reading);
    }
    echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
}
