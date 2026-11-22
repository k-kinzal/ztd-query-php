"""Recompute the retained MySQL unsigned conversions and compounds on every pinned release."""

import run_server_mysql_unsigned_casts
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_unsigned_casts)
