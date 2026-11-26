"""Observe cache cookies, fresh connection state and native spill activity."""

import ctypes
from pathlib import Path

from run import require
from run_sqlite_create_table import quoted
from sqlite_cache_cases import DEFAULT, LIVE, SPILL
from sqlite_header_fixture import databases, rows


def populate(db, item, *, pressure=False):
    db.execute("ATTACH 'attached.sqlite' AS " + quoted(item["attachment"]))
    for index, target in enumerate(("main", item["attachment"])):
        schema = quoted(target)
        db.execute(f"PRAGMA {schema}.page_size={item['pageSize']}")
        if pressure:
            db.execute(f"CREATE TABLE {schema}.kept(id INTEGER PRIMARY KEY, data BLOB);"
                       f"INSERT INTO {schema}.kept VALUES (0,X'01'); PRAGMA {schema}.cache_size=6;"
                       f"PRAGMA {schema}.cache_spill=1")
        else:
            db.execute(f"CREATE TABLE {schema}.kept(id INTEGER PRIMARY KEY, value INT);"
                       f"INSERT INTO {schema}.kept VALUES ({index+1},{11+item['marker']}),({index+3},NULL);"
                       f"PRAGMA {schema}.default_cache_size={13+index}; PRAGMA {schema}.cache_spill={32+index};"
                       f"PRAGMA {schema}.cache_size={17+index}")
    if not item["spilling"]:
        db.execute("PRAGMA cache_spill=OFF")


def physical_cookie(filename, files):
    path = Path(filename)
    require(path.parent.resolve() == Path.cwd() and path.name in ("primary.sqlite", "attached.sqlite"),
            "sqlite-cache-unexpected-path", filename)
    with path.open("rb") as file:
        header = file.read(100)
    require(len(header) == 100 and header.startswith(b"SQLite format 3\x00"), "sqlite-cache-file-header", filename)
    value = int.from_bytes(header[48:52], "big", signed=True)
    files.setdefault(path.name, []).append({"pageOneHex": header.hex(), "cookie": value})
    return value


def scalar(db, statement):
    response = rows(db, statement)
    require(len(response) == 1 and len(response[0]) == 1 and response[0][0][0] == "integer",
            "sqlite-cache-native-state-shape", [statement, response])
    return int(response[0][0][1])


def snapshot(db, files):
    attached, state = databases(db), {}
    for _, name, location in attached:
        target, filename = name[1], location[1]
        schema = quoted(target)
        settings = {field: scalar(db, f"PRAGMA {schema}.{pragma}")
                    for field, pragma in (("live", LIVE), ("default", DEFAULT), ("spill", SPILL), ("pageSize", "page_size"))}
        settings["cookie"] = physical_cookie(filename, files) if filename else None
        if filename:
            location[1] = Path(filename).name
        settings["tables"] = rows(db, f"SELECT name FROM {schema}.sqlite_schema WHERE type='table' ORDER BY name")
        settings["population"] = rows(db, f"SELECT id,value FROM {schema}.kept ORDER BY id") if settings["tables"] else []
        state[target] = settings
    return {"attached": attached, "databases": state}


def spill_count(db, *, reset):
    api = db.lib.sqlite3_db_status
    api.argtypes = (ctypes.c_void_p, ctypes.c_int, ctypes.POINTER(ctypes.c_int), ctypes.POINTER(ctypes.c_int), ctypes.c_int)
    api.restype = ctypes.c_int
    current, high = ctypes.c_int(), ctypes.c_int()
    db.check(api(db.db, 12, ctypes.byref(current), ctypes.byref(high), int(reset)))
    return current.value


def write_pressure(db, scope):
    schema = quoted(scope)
    present = scalar(db, f"PRAGMA {schema}.cache_spill")
    spill_count(db, reset=True)
    db.execute("WITH RECURSIVE n(i) AS (VALUES (1) UNION ALL SELECT i+1 FROM n WHERE i<8) "
               f"INSERT INTO {schema}.kept SELECT i,zeroblob(4096) FROM n")
    spills = spill_count(db, reset=True)
    population = scalar(db, f"SELECT count(*) FROM {schema}.kept")
    db.execute("ROLLBACK")
    require(db.get_autocommit(db.db), "sqlite-cache-pressure-unclosed-transaction")
    return {"spills": spills, "visible": present, "rowCount": population,
            "restored": rows(db, f"SELECT id,data FROM {schema}.kept ORDER BY id")}
