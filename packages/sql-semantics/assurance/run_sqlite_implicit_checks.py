"""Check SQLite implicit comparison names and the effects of native column rename."""

import ctypes
from hashlib import sha256

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_named_checks import create
from run_sqlite_unique_keys import UniqueDatabase, values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_implicit_check_cases import RENAMES, SYMBOLS, case, expected, finite_id, finite_ids, negatives, requests, write_rows
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-implicit-checks-v1.json"
CONTRACT = "94101eaf66395a96c32aca5cc6e4b79f210b0436962f1712c08e0fc14f75b766"


FIXTURE = ""


class ImplicitCheckDatabase(UniqueDatabase):
    policy_digest = sha256(f"{UniqueDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()


def read(db, target):
    return db.observe(f"SELECT * FROM {target}", {}, preserve_order=True)


def observe(db, item, sql):
    table = f"main.{quoted(item['table'])}"
    slot = item["checks"][-1]["condition"][2][1]
    name = item["columns"][slot][0]
    cases = {}
    for mode, destination in RENAMES.items():
        create(db, item, sql)
        try:
            if destination is not None:
                spelling = quoted(destination) if mode == "quoted" else destination
                db.execute(f"ALTER TABLE {table} RENAME COLUMN {quoted(name)} TO {spelling}")
            writes = []
            for values in write_rows(item):
                result = db.observe(f"INSERT INTO {table} VALUES {values_sql([values])}", {}, capture_evaluation_error=True)
                writes.append({"error": result["evaluationError"], "rows": read(db, table)["rows"]})
                db.execute(f"DELETE FROM {table}")
            cases[mode] = {"fields": read(db, table)["names"], "writes": writes}
        finally:
            db.execute(f"DROP TABLE {table}")
    return cases


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed" and response["operation"] == "create-table", "sqlite-implicit-check-rejected", response)
    require(all(response[name] == item[name] for name in item), "sqlite-implicit-check-structure",
            {"request": item, "response": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    measurements = {"observation": observe(db, item, response["sql"])}
    if original is not None:
        measurements["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **measurements)
    target = expected(item)
    for actual in measurements.values():
        compare(target, actual)
    if ledger is not None:
        ledger.record(key, "compared")


def native_keywords(db, lexical):
    count = db.lib.sqlite3_keyword_count
    count.restype = ctypes.c_int
    read_keyword = db.lib.sqlite3_keyword_name
    read_keyword.restype = ctypes.c_int
    read_keyword.argtypes = [ctypes.c_int, ctypes.POINTER(ctypes.c_char_p), ctypes.POINTER(ctypes.c_int)]
    names, observed = set(), {}
    for index in range(count()):
        pointer, length = ctypes.c_char_p(), ctypes.c_int()
        require(read_keyword(index, ctypes.byref(pointer), ctypes.byref(length)) == 0, "sqlite-implicit-check-keyword-api", index)
        word = ctypes.string_at(pointer, length.value).decode("ascii")
        require(word.isupper() and word.replace("_", "").isalpha() and word not in names, "sqlite-implicit-check-keyword", word)
        names.add(word)
        sql = f"CREATE TABLE main.ir_check_keyword ({quoted(word)} INTEGER CHECK ({word}>0))"
        db.execute("SAVEPOINT implicit_keyword")
        try:
            db.execute(sql)
            passed = db.observe("INSERT INTO main.ir_check_keyword VALUES (1)", {}, capture_evaluation_error=True)["evaluationError"]
            failed = db.observe("INSERT INTO main.ir_check_keyword VALUES (-1)", {}, capture_evaluation_error=True)["evaluationError"]
            accepted = passed is None and failed == {"code": 19, "message": f"CHECK constraint failed: {word}>0"}
            observed[word] = {"sql": sql, "accepted": accepted, "positive": passed, "negative": failed}
        except RuntimeError as error:
            require(str(error).startswith("SQLite 1: "), "sqlite-implicit-check-unexpected-keyword-error", str(error))
            observed[word] = {"sql": sql, "accepted": False, "nativeRejection": str(error)}
        finally:
            db.execute("ROLLBACK TO implicit_keyword; RELEASE implicit_keyword")
    rejected = {name for name, value in observed.items() if not value["accepted"]}
    require(len(names) == lexical["keywordCount"] and rejected == set(lexical["bareRejected"]), "sqlite-implicit-check-keyword-domain",
            {"count": len(names), "rejected": sorted(rejected)})
    return observed


def controls(db, lexical):
    model = case("==", zeroes=2)
    sound = expected(model)
    corrupt = {**sound, "bare": sound["original"]}
    compare(sound, sound)
    try:
        compare(sound, corrupt)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "sqlite-implicit-check-comparator-signal", error.signal)
    else:
        raise Failure("sqlite-implicit-check-comparator-accepted")
    return {"expected": sound, "invalid": corrupt, "nativeKeywords": native_keywords(db, lexical)}


def forms(response):
    clause = response["checks"][-1]
    label = clause["label"]
    yield "owner/table" if clause["column"] is None else f"owner/{clause['column']}"
    yield "quoted" if label["quoted"] else "bare"
    yield f"slot/{clause['condition'][2][1]}"
    yield "glyph/" + label["glyph"]
    yield "zeroes/" + str(label["zeroes"])
    if label["name"] == "":
        yield "name/empty"
    if any(ord(char) > 127 for char in label["name"]):
        yield "name/non-ASCII"
    if clause["condition"][3][1] == "9223372036854775807":
        yield "literal/int64-upper"
    if "\t" in label["before"] + label["after"]:
        yield "space/tab"


def required(_domain):
    return {"owner/table", "owner/0", "owner/1", "quoted", "bare", "slot/0", "slot/1", "zeroes/0", "zeroes/32",
            "name/empty", "name/non-ASCII", "literal/int64-upper", "space/tab", *("glyph/" + glyph for glyph in SYMBOLS)}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required,
                              lambda: controls(db, specification["lexicalDomain"]))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    parent = "sql-semantics-sqlite/src/Ir/Schema/"
    label = parent + "Predicate/ImplicitCheckLabel.php"
    renderer = parent + "TableDeclarationSql.php"
    finish = "$check->label->expression($condition->right)"
    model = case("==", boundary="1", zeroes=2)
    return runner(args, db, [
        RenderFault("quote-implicit-name", label, "$this->quoted ?", "true ?", model, "meaning-mismatch"),
        RenderFault("canonicalize-implicit-glyph", label, "$this->token->value", "$this->operation()->value", model, "meaning-mismatch"),
        RenderFault("erase-implicit-zeroes", label, "str_repeat('0', $this->leadingZeroes)", "''", model, "meaning-mismatch"),
        RenderFault("freeze-name-across-rename", renderer, "return 'CHECK (' . " + finish,
                    "return 'CONSTRAINT \"A==001\" CHECK (' . " + finish, model, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("implicit-checks", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, ImplicitCheckDatabase)
