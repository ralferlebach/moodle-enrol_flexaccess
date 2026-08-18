# enrol_flexaccess — JMeter load test

A read-endpoint plateau load test (AP6). It ramps up `threads` virtual users over `rampup`
seconds and keeps them active for `duration` seconds, repeatedly requesting a read endpoint and
asserting HTTP 200.

## Run locally

```bash
make jmeter BASE_URL=http://localhost:8000        # downloads JMeter on first run
# or manually, after seeding:
eval "$(php tests/load/seed_large.php)"
apache-jmeter/bin/jmeter -n -t tests/load/flexaccess-read-endpoints.jmx \
  -Jbase_url="$BASE_URL" -Jthreads=25 -Jrampup=10 -Jduration=90 -l results.jtl
```

## Scope

Smoke-level: it exercises a public read path under concurrency to catch gross regressions. Load
tests for the account-creation + capacity-lock + mailqueue paths are tracked with the P0 access
work and will be added as those flows stabilise.
