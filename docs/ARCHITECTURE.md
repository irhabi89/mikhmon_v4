# Mikhmon architecture

## Layers

- Entry points (`index.php`, `admin.php`, `status/index.php`, `process/*.php`)
  bootstrap the kernel and dispatch a route. They must stay thin.
- `src/Http` handles request/response, routing, views, auth and CSRF.
- `src/Controllers` contains one controller per page/action.
- `resources/views` contains layouts, partials and reusable components.
- `src/Storage` owns SQLite access (PDO, migrations, repositories).
- `src/Sync` owns the streamed router -> snapshot -> sales pipeline.
- `src/Router` owns RouterOS transport and connection policy.
- `src/Support` contains small, framework-free helpers.
- `bin/mikhmon` is the CLI (migrate, maintenance, sync, import).

## Data flow

```
RouterOS /system/script
  -> Scanner (commStream, one row at a time)
  -> sales_router_records (snapshot + queue)
  -> Importer (batched)
  -> sales_records (report table, indexed)
  -> SalesRepository
  -> Controllers -> Views
```

Reports never open a router connection. Only sync/probe actions do.

## Sales record format

Nine legacy fields separated by `-|-`:

```
date | time | user | price | address | mac | validity | profile | comment
```

Server-aware records append:

```
|#MMSRV1#|<hotspot-server-name>
```

`RecordParser` strips the suffix before parsing the nine columns. Records
without the suffix have `server_name = ''` (legacy/unknown) and are exposed
through the "Tanpa server" filter.

## Rules

- Do not put SQL or router calls in views.
- Escape output with the view helper.
- Use prepared statements for all user input.
- Mutating POST actions require a CSRF token.
- Keep legacy URL query parameters (`report`, `hotspot`, `ppp`, `admin id`).
- Prefer adding a service/repository over growing a page file.
