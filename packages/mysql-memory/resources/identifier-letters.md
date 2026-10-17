# Identifier character observations

`identifier-letters.json` contains compact filename spellings observed through
SQL errors from independent MySQL 5.6.51 and 5.7.44 Testcontainers instances.
It contains no server implementation code. The general character classes and
reserved-name suffix are documented in the
[public identifier mapping manual](https://dev.mysql.com/doc/refman/8.0/en/identifier-mapping.html).

To obtain each observation, create and select database `d`, set the client
character set to `utf8mb4`, and issue `RENAME TABLE x<character>y TO other` with
the source identifier quoted. Error 1017 contains `./d/x<encoding>y.frm`.
The observations include every code point in these public-manual ranges:
U+00C0–U+02AF, U+0370–U+058F, U+1E00–U+1FFF, U+2160–U+217F,
U+24B6–U+24E9, and U+FF21–U+FF5A.

Both releases agree on all 1,694 observations. The 1,510 entries that differ
from the ordinary `@` followed by four lowercase hexadecimal digits are
retained. Other characters use the documented hexadecimal form. This lookup
does not depend on the host's current Unicode case-conversion version.
