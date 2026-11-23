"""Independently replay native MySQL truncation effects."""

import run_server_mysql_truncate
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_truncate)
