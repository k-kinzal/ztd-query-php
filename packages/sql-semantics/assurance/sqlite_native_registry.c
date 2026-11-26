/* Observe the native registration tables through the fixed amalgamation. */
#include "sqlite3.c"

static const char *encoding(unsigned int flags)
{
    const char *encodings[] = {NULL, "utf8", "utf16le", "utf16be"};
    return encodings[flags & SQLITE_FUNC_ENCMASK];
}

static void functions(FuncDef *registry, int builtin)
{
    const unsigned int stored = SQLITE_DETERMINISTIC | SQLITE_DIRECTONLY | SQLITE_SUBTYPE | SQLITE_INNOCUOUS;
    for (const FuncDef *function = registry; function != NULL; function = function->pNext) {
        if (!function->xSFunc || (function->funcFlags & SQLITE_FUNC_INTERNAL)) {
            continue;
        }
        const char type = function->xValue ? 'w' : function->xFinalize ? 'a' : 's';
        const unsigned int flags = (function->funcFlags & stored) ^ SQLITE_INNOCUOUS;
        printf("F\t%s\t%d\t%c\t%s\t%d\t%u\n", function->zName, builtin, type,
               encoding(function->funcFlags), (int)function->nArg, flags);
    }
}

int main(void)
{
    sqlite3 *db = NULL;
    if (sqlite3_open(":memory:", &db) != SQLITE_OK) {
        return 1;
    }
    printf("S\t%s\n", sqlite3_sourceid());
    for (int slot = 0; slot < SQLITE_FUNC_HASH_SZ; ++slot) {
        for (FuncDef *group = sqlite3BuiltinFunctions.a[slot]; group; group = group->u.pHash) {
            functions(group, 1);
        }
    }
    for (HashElem *element = sqliteHashFirst(&db->aFunc); element; element = sqliteHashNext(element)) {
        functions(sqliteHashData(element), 0);
    }
    for (HashElem *element = sqliteHashFirst(&db->aCollSeq); element; element = sqliteHashNext(element)) {
        CollSeq *collation = sqliteHashData(element);
        printf("C\t%s\n", collation->zName);
    }
    for (HashElem *element = sqliteHashFirst(&db->aModule); element; element = sqliteHashNext(element)) {
        Module *module = sqliteHashData(element);
        printf("M\t%s\n", module->zName);
    }
    return sqlite3_close(db) != SQLITE_OK;
}
