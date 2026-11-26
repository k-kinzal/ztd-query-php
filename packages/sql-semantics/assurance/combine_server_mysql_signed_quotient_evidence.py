"""Replay all nine pinned MySQL signed quotient profiles."""

import run_server_mysql_signed_quotient
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_signed_quotient)
