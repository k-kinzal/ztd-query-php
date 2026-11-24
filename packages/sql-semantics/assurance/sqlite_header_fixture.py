"""Build independently inspectable SQLite page-one and transaction fixtures."""

import ctypes

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

FILE_FIELDS = {"schema_version": 40, "user_version": 60, "application_id": 68, "freelist_count": 36}
COOKIE = {"schema_version": 1, "user_version": 6, "application_id": 8, "freelist_count": 0, "data_version": 15}


def empty_header():
    return dict.fromkeys(FILE_FIELDS, 0)


def initial_names(item):
    return {"main": 0, **({"temp": 1} if item["temp"] else {}), item["attachment"]: 2}


def selected_cookie_seeds(item, slot):
    return item["seed"] + slot, item["seed"] + 13 + slot


def expected_headers(item):
    output = {}
    for name, slot in initial_names(item).items():
        user, application = selected_cookie_seeds(item, slot)
        output[name] = {"schema_version": 3, "user_version": user, "application_id": application, "freelist_count": 1}
    return output


def expected_population(item):
    return {name: result(rows=((10 + slot,), (20 + slot,)))["rows"] for name, slot in initial_names(item).items()}


def config_defensive(db, value=-1):
    api = db.lib.sqlite3_db_config
    api.argtypes, api.restype = (ctypes.c_void_p, ctypes.c_int), ctypes.c_int
    state = ctypes.c_int(-1)
    db.check(api(db.db, 1010, ctypes.c_int(value), ctypes.byref(state)))
    require(state.value in (0, 1), "sqlite-header-defensive-state", state.value)
    return bool(state.value)


def serialized_header(db, schema):
    api, free = db.lib.sqlite3_serialize, db.lib.sqlite3_free
    api.argtypes = (ctypes.c_void_p, ctypes.c_char_p, ctypes.POINTER(ctypes.c_int64), ctypes.c_uint)
    api.restype = ctypes.c_void_p
    free.argtypes, free.restype = (ctypes.c_void_p,), None
    size = ctypes.c_int64()
    pointer = api(db.db, schema.encode(), ctypes.byref(size), 0)
    if not pointer:
        require(size.value in (-1, 0), "sqlite-header-serialization-unavailable", [schema, size.value])
        return empty_header()
    try:
        require(size.value >= 100, "sqlite-header-page-one-unavailable", [schema, size.value])
        header = ctypes.string_at(pointer, 100)
        require(header.startswith(b"SQLite format 3\x00"), "sqlite-header-file-format", schema)
        return {field: int.from_bytes(header[offset:offset + 4], "big", signed=field != "freelist_count")
                for field, offset in FILE_FIELDS.items()}
    finally:
        free(pointer)


def rows(db, statement):
    return db._observe(statement, {}, preserve_order=True)["rows"]


def databases(db):
    return rows(db, "PRAGMA database_list")


def populate(db, item):
    db.execute("ATTACH ':memory:' AS " + quoted(item["attachment"]))
    for schema, slot in initial_names(item).items():
        scope = quoted(schema)
        user, application = selected_cookie_seeds(item, slot)
        db.execute(f"CREATE TABLE {scope}.kept(id); INSERT INTO {scope}.kept VALUES ({10+slot}),({20+slot}); "
                   f"PRAGMA {scope}.user_version={user}; PRAGMA {scope}.application_id={application}; "
                   f"CREATE TABLE {scope}.released(dummy); DROP TABLE {scope}.released")
    require(config_defensive(db, int(item["defensive"])) == item["defensive"], "sqlite-header-defensive-configure")
    if item["transaction"]:
        db.execute("BEGIN")


def fixture(db, population_names):
    attached = databases(db)
    header = {row[1][1]: serialized_header(db, row[1][1]) for row in attached}
    population = {name: rows(db, f"SELECT id FROM {quoted(name)}.kept ORDER BY id") for name in population_names}
    return {"attached": attached, "headers": header, "population": population, "transaction": not bool(db.get_autocommit(db.db)),
            "defensive": config_defensive(db)}
