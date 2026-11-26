# Runtime data directory

This directory intentionally stays inside the project when deploying with
`MIKHMON_DB_PATH=data/mikhmon.db`.

```text
data/
├── mikhmon.db       # SQLite database (not committed)
├── backups/         # db:migrate / on-login backups
├── cache/           # short-lived ResultCache files
├── locks/           # sync locks
├── logs/            # application/sync logs
└── .htaccess        # denies direct HTTP access
```

## Security

- `.htaccess` files deny direct HTTP access. Test:
  `https://your-domain/data/mikhmon.db` must return 403/404.
- On Nginx without `.htaccess`, move the database outside the document root.
- Never commit `mikhmon.db`, `.db-wal`, `.db-shm`, or backups.

## Permissions

The cPanel PHP user must own and be able to write these directories:

```bash
chmod 750 data
chmod -R u+rwX data
```

Do not use `777`.

See [`docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md) for the full cPanel guide.
