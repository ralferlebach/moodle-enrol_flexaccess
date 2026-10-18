/*
 * This file is part of Moodle - https://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * TLS front for the two-site LTI 1.3 suite (node core only, no dependencies).
 *
 * LTI 1.3 needs HTTPS for real: the OIDC flow posts across sites, and Moodle's LTI cookies are only
 * SameSite=None when the site is served over HTTPS. Each site runs on PHP's built-in server; this
 * proxy terminates TLS with a test CA certificate and forwards to it, keeping the Host header.
 *
 * Usage: node tls-proxy.js <listenport> <targetport> <certfile> <keyfile>
 */

'use strict';

const fs = require('fs');
const http = require('http');
const https = require('https');

const [listen, target, cert, key] = process.argv.slice(2);
if (!listen || !target || !cert || !key) {
  process.stderr.write('Usage: node tls-proxy.js <listenport> <targetport> <certfile> <keyfile>\n');
  process.exit(2);
}

const server = https.createServer({ cert: fs.readFileSync(cert), key: fs.readFileSync(key) }, (req, res) => {
  const upstream = http.request({
    host: '127.0.0.1',
    port: Number(target),
    method: req.method,
    path: req.url,
    headers: { ...req.headers, 'x-forwarded-proto': 'https' },
  }, (response) => {
    res.writeHead(response.statusCode, response.headers);
    response.pipe(res);
  });
  upstream.on('error', (error) => {
    res.writeHead(502, { 'content-type': 'text/plain' });
    res.end(`Upstream error: ${error.message}`);
  });
  req.pipe(upstream);
});
server.keepAliveTimeout = 65 * 1000;
server.listen(Number(listen), '127.0.0.1', () => {
  process.stdout.write(`TLS proxy https://127.0.0.1:${listen} -> http://127.0.0.1:${target}\n`);
});
