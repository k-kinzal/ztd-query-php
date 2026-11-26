"""Replay signed range evidence on every pinned MySQL release."""

import run_server_mysql_signed_range
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_signed_range)
