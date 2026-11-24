"""Minimal libpq observer for command status, SQLSTATE, and ordered notices."""

import ctypes as c
import ctypes.util
import os
import re

from run import require

NOTICE = c.CFUNCTYPE(None, c.c_void_p, c.c_void_p)


def library():
    name = ctypes.util.find_library("pq")
    require(name is not None, "postgres-libpq-missing")
    lib = c.CDLL(name)
    signatures = (
        ("PQconnectdbParams", c.c_void_p, c.POINTER(c.c_char_p), c.POINTER(c.c_char_p), c.c_int),
        ("PQstatus", c.c_int, c.c_void_p),
        ("PQfinish", None, c.c_void_p),
        ("PQexec", c.c_void_p, c.c_void_p, c.c_char_p),
        ("PQclear", None, c.c_void_p),
        ("PQresultStatus", c.c_int, c.c_void_p),
        ("PQcmdStatus", c.c_char_p, c.c_void_p),
        ("PQresultErrorField", c.c_char_p, c.c_void_p, c.c_int),
        ("PQntuples", c.c_int, c.c_void_p),
        ("PQnfields", c.c_int, c.c_void_p),
        ("PQfname", c.c_char_p, c.c_void_p, c.c_int),
        ("PQftype", c.c_uint, c.c_void_p, c.c_int),
        ("PQgetisnull", c.c_int, c.c_void_p, c.c_int, c.c_int),
        ("PQgetvalue", c.c_char_p, c.c_void_p, c.c_int, c.c_int),
        ("PQtransactionStatus", c.c_int, c.c_void_p),
        ("PQsetNoticeReceiver", c.c_void_p, c.c_void_p, NOTICE, c.c_void_p),
        ("PQlibVersion", c.c_int),
    )
    for name, result, *args in signatures:
        function = getattr(lib, name)
        function.restype = result
        function.argtypes = args
    return lib


LIB = library()


def ascii_text(value):
    return value.decode("utf-8") if value is not None else None


def notice_fields(result):
    # Nonlocalized severity, SQLSTATE, message, detail and hint.
    return [ascii_text(LIB.PQresultErrorField(result, ord(field))) for field in "VCMDH"]


def raw_connection():
    prefix = "ZTD_IR_POSTGRES_"
    dsn = os.environ[prefix + "DSN"]
    match = re.fullmatch(r"pgsql:host=(?:127[.]0[.]0[.]1|localhost);port=([1-9][0-9]{0,4});dbname=ir_fixture", dsn)
    require(match is not None, "postgres-observer-requires-disposable-fixture")
    keys = (c.c_char_p * 8)(b"host", b"port", b"dbname", b"user", b"password", b"connect_timeout", b"sslmode", None)
    values = (c.c_char_p * 8)(b"127.0.0.1", match[1].encode(), b"ir_fixture",
                             os.environ[prefix + "USER"].encode(), os.environ[prefix + "PASSWORD"].encode(),
                             b"5", b"disable", None)
    connection = LIB.PQconnectdbParams(keys, values, 0)
    require(bool(connection), "postgres-libpq-null-connection")
    if LIB.PQstatus(connection) != 0:
        LIB.PQfinish(connection)
        require(False, "postgres-libpq-fixture-connection")
    return connection


class Session:
    def __enter__(self):
        self.connection = raw_connection()
        self.notices = []
        self.receiver = NOTICE(lambda _, result: self.notices.append(notice_fields(result)))
        LIB.PQsetNoticeReceiver(self.connection, self.receiver, None)
        self.execute("SET standard_conforming_strings=on; SET client_encoding='UTF8';"
                     "SET search_path=pg_catalog, public; SET statement_timeout='5s'; SET client_min_messages=warning")
        require(self.execute("SELECT current_database()") == [["ir_fixture"]], "postgres-wrong-native-fixture")
        return self

    def __exit__(self, *_):
        LIB.PQfinish(self.connection)

    def run(self, sql, describe=False, diagnostic_error=False):
        self.notices.clear()
        result = LIB.PQexec(self.connection, sql.encode("utf-8"))
        require(bool(result), "postgres-libpq-null-result")
        try:
            status = LIB.PQresultStatus(result)
            if status == 7:
                fields = notice_fields(result)
                return {"sqlState": fields[1], "command": None, "notices": list(self.notices), "rows": None,
                        **({"error": fields} if diagnostic_error else {})}
            require(status in (1, 2), "postgres-libpq-result-status", status)
            rows = [
                [None if LIB.PQgetisnull(result, r, k) else ascii_text(LIB.PQgetvalue(result, r, k))
                 for k in range(LIB.PQnfields(result))]
                for r in range(LIB.PQntuples(result))
            ]
            observed = {"sqlState": "00000", "command": ascii_text(LIB.PQcmdStatus(result)),
                        "notices": list(self.notices), "rows": rows}
            if diagnostic_error:
                observed["error"] = None
            if describe:
                observed["columns"] = [[ascii_text(LIB.PQfname(result, k)), LIB.PQftype(result, k)]
                                       for k in range(LIB.PQnfields(result))]
            return observed
        finally:
            LIB.PQclear(result)

    def execute(self, sql):
        result = self.run(sql)
        require(result["sqlState"] == "00000", "postgres-index-fixture-sql", {"sql": sql, "sqlState": result["sqlState"]})
        return result["rows"]

    def transaction(self):
        return {0: "idle", 2: "active", 3: "failed"}[LIB.PQtransactionStatus(self.connection)]
