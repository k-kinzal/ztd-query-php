# Unresolved native reference observations

A native timeout is not a matching SQL error and is not a permitted comparison
exception. Keep its input separate from completed differential observations and
retain it until a reference outcome can be established. The acceptance contract
remains open while these inputs have no comparison result.

## Compressed tablespace extension

This request did not complete on the disposable MySQL 8.0.44 and 8.4.7 images:

```sql
CREATE TABLESPACE blocked ADD DATAFILE 'blocked.ibd'
  FILE_BLOCK_SIZE 1024 AUTOEXTEND_SIZE 4195328;
```

On 8.0.44, `SHOW FULL PROCESSLIST` still reported the request as `starting` after
216 seconds. `KILL QUERY` did not terminate it. The container used one CPU core
and approximately 27 GiB of memory, so the test process and its dedicated
container were terminated. This was not an emulator observation.

A fresh 8.4.7 instance reproduced the noncompletion with a five-second mysqli
read deadline. The client returned error 2006 and could not read warnings. That
error is a consequence of the client deadline; it is not the SQL outcome of the
request and must not be compared with an emulator SQL error.

The request and the adjacent page/block combinations are preserved in
[`tablespace-reference-pending.json`](../tests/Integration/Fuzz/fixtures/tablespace-reference-pending.json).
They are pending reference investigation, not passing cases of
`TablespaceOptionOrderTest`. Do not run them without a client deadline and a
separate disposable container. Neither the pending inputs nor the supplied SQL
Faker seeds are deleted or counted as matches.

The completed observations for the default block size show that the byte range
is checked before the multiple of complete 16 KiB pages. They do not establish
what a successful compressed-block request should do at every extension size.
