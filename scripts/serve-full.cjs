const { spawn } = require('node:child_process');
const { createServer } = require('node:net');
const path = require('node:path');

const cwd = path.resolve(__dirname, '..');
const children = [];
let stopping = false;

function stop(exitCode) {
  if (stopping) return;
  stopping = true;
  process.exitCode = exitCode;
  for (const child of children) {
    if (child.exitCode === null && child.signalCode === null) child.kill();
  }
}

function start(name, command, args) {
  const child = spawn(command, args, { cwd, stdio: 'inherit', windowsHide: true });
  children.push(child);
  child.once('error', (error) => {
    console.error(`${name}: ${error.message}`);
    stop(1);
  });
  child.once('exit', (code) => {
    if (stopping) return;
    console.error(`${name} stopped.`);
    stop(code || 1);
  });
}

process.once('SIGINT', () => stop(130));
process.once('SIGTERM', () => stop(143));

const port = Number(process.env.APP_PORT || '18180');
const signingPort = Number.parseInt(process.env.A_BOGUS_PORT || '9876', 10);
if (!Number.isInteger(port) || port < 1 || port > 65535 || port === signingPort) {
  console.error('APP_PORT must be a valid port different from A_BOGUS_PORT.');
  process.exitCode = 1;
} else {
  // Windows PHP may report a successful start even when the HTTP port is in use.
  const probe = createServer();
  probe.once('error', (error) => {
    console.error(`PHP port ${port}: ${error.message}`);
    stop(1);
  });
  probe.listen(port, '127.0.0.1', () => {
    probe.close(() => {
      if (stopping) return;
      start('a_bogus', process.execPath, ['scripts/a_bogus_server.js']);
      start('PHP', 'php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'public/router.php']);
    });
  });
}
