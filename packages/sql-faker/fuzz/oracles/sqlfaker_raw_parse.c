#include "postgres.h"
#include "fmgr.h"
#include "utils/builtins.h"
#include "parser/parser.h"

#if PG_VERSION_NUM != 170002
#error SQLFaker raw parser helper requires PostgreSQL 17.2 headers
#endif

PG_MODULE_MAGIC;
PG_FUNCTION_INFO_V1(sqlfaker_raw_parse);

Datum sqlfaker_raw_parse(PG_FUNCTION_ARGS)
{
    int32 mode = PG_GETARG_INT32(1);
    char *sql;
    List *trees;
    if (mode < RAW_PARSE_DEFAULT || mode > RAW_PARSE_PLPGSQL_ASSIGN3)
        ereport(ERROR, (errcode(ERRCODE_INVALID_PARAMETER_VALUE), errmsg("invalid raw parse mode")));
    sql = text_to_cstring(PG_GETARG_TEXT_PP(0));
    trees = raw_parser(sql, (RawParseMode) mode);
    PG_RETURN_INT32(list_length(trees));
}
