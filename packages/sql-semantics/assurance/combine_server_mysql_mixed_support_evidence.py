"""Replay native mixed MySQL secondary and foreign-key operation evidence."""

from combine_server_join_evidence import main
import run_server_mysql_mixed_support

if __name__ == "__main__":
    main(run_server_mysql_mixed_support)
