# Code coverage gate

The ecosystem CI job (`main.yml`) runs PHPUnit with pcov coverage and fails the build when
line coverage drops below a floor.

- **Current floor:** 25% (conservative, guards against a coverage collapse).
- **Driver:** pcov (`coverage: pcov` in the setup-php step).
- **How it is measured:** `moodle-plugin-ci phpunit --coverage-text --coverage-pcov`; the
  workflow parses the `Lines:` summary and compares it against the floor.

## Raising the floor

After the first green run, note the measured percentage from the job log
("Measured line coverage: NN%") and raise `floor` in `main.yml` to a value slightly below it
(for example, measured minus 3–5 points) so the gate ratchets coverage upward over time without
becoming flaky.
