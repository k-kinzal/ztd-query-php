# Unresolved native reference observations

A native timeout is not a matching SQL error and is not a permitted comparison
exception. Keep its input separate from completed differential observations and
retain it until a reference outcome can be established. These inputs have no established comparison result.

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

A second bounded 8.4.7 probe used fresh containers with a 2 GiB memory limit
and compared `innodb_extend_and_initialize=OFF` and `ON`. Both exceeded the
five-second client deadline (5.004 and 5.002 seconds). Disabling physical page
initialization therefore did not establish a reference result. Each dedicated
container was terminated afterward. These observations do not identify the
server's internal cause or change the pending classification.

The pending requests and adjacent page/block combinations were:

```sql
CREATE TABLESPACE block1024size4195327 ADD DATAFILE 'block1024size4195327.ibd' FILE_BLOCK_SIZE 1024 AUTOEXTEND_SIZE 4195327;
CREATE TABLESPACE block1024size4195328 ADD DATAFILE 'block1024size4195328.ibd' FILE_BLOCK_SIZE 1024 AUTOEXTEND_SIZE 4195328;
CREATE TABLESPACE block1024size4210687 ADD DATAFILE 'block1024size4210687.ibd' FILE_BLOCK_SIZE 1024 AUTOEXTEND_SIZE 4210687;
CREATE TABLESPACE block1024size4210688 ADD DATAFILE 'block1024size4210688.ibd' FILE_BLOCK_SIZE 1024 AUTOEXTEND_SIZE 4210688;
CREATE TABLESPACE block4096size4195327 ADD DATAFILE 'block4096size4195327.ibd' FILE_BLOCK_SIZE 4096 AUTOEXTEND_SIZE 4195327;
CREATE TABLESPACE block4096size4195328 ADD DATAFILE 'block4096size4195328.ibd' FILE_BLOCK_SIZE 4096 AUTOEXTEND_SIZE 4195328;
CREATE TABLESPACE block4096size4210687 ADD DATAFILE 'block4096size4210687.ibd' FILE_BLOCK_SIZE 4096 AUTOEXTEND_SIZE 4210687;
CREATE TABLESPACE block4096size4210688 ADD DATAFILE 'block4096size4210688.ibd' FILE_BLOCK_SIZE 4096 AUTOEXTEND_SIZE 4210688;
```

These observations remain unresolved. They are not passing executions; a timeout
from the SQL Faker PHP-Fuzzer target must still fail the property.

The completed observations for the default block size show that the byte range
is checked before the multiple of complete 16 KiB pages. They do not establish
what a successful compressed-block request should do at every extension size.
