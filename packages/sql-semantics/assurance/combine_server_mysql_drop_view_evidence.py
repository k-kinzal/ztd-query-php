"""Independently replay MySQL DROP VIEW catalog and transaction evidence."""

import run_server_mysql_drop_view
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_drop_view)
