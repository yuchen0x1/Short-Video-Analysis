const { spawn } = require('node:child_process');
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

const port = process.env.APP_PORT || '18180';
start('a_bogus', process.execPath, ['scripts/a_bogus_server.js']);
start('PHP', 'php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'public/router.php']);
