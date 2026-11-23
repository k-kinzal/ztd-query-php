"""Replay MySQL signed table declaration evidence."""

import run_server_mysql_create_signed
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_signed)
