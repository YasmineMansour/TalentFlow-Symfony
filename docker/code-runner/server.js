const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFile } = require('child_process');

const HOST = process.env.RUNNER_HOST || '0.0.0.0';
const PORT = Number(process.env.RUNNER_PORT || 9001);
const MAX_CODE_SIZE = 50_000;
const EXEC_TIMEOUT_MS = Number(process.env.RUNNER_TIMEOUT_MS || 5000);

const languageConfig = {
  php: { command: 'php', extension: 'php' },
  python: { command: 'python3', extension: 'py' },
  javascript: { command: 'node', extension: 'js' },
};

function sendJson(res, statusCode, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

function parseBody(req) {
  return new Promise((resolve, reject) => {
    let data = '';
    req.on('data', chunk => {
      data += chunk;
      if (data.length > MAX_CODE_SIZE * 2) {
        reject(new Error('Payload too large'));
        req.destroy();
      }
    });
    req.on('end', () => {
      try {
        resolve(JSON.parse(data || '{}'));
      } catch (error) {
        reject(error);
      }
    });
    req.on('error', reject);
  });
}

async function executeCode(language, code, stdin) {
  const config = languageConfig[language];
  if (!config) {
    throw new Error('Unsupported language');
  }

  const tempDir = await fs.promises.mkdtemp(path.join(os.tmpdir(), 'tf-runner-'));
  const filePath = path.join(tempDir, `main.${config.extension}`);
  await fs.promises.writeFile(filePath, code, 'utf8');

  const startedAt = Date.now();

  return new Promise(resolve => {
    const child = execFile(
      config.command,
      [filePath],
      {
        timeout: EXEC_TIMEOUT_MS,
        maxBuffer: 1024 * 1024,
      },
      async (error, stdout, stderr) => {
        const durationMs = Date.now() - startedAt;
        const timedOut = Boolean(error && error.killed && error.signal);
        const exitCode = error && typeof error.code === 'number' ? error.code : 0;

        try {
          await fs.promises.rm(tempDir, { recursive: true, force: true });
        } catch (_) {}

        resolve({
          stdout: stdout || '',
          stderr: stderr || (error && !timedOut ? String(error.message || '') : ''),
          exitCode,
          timedOut,
          durationMs,
        });
      }
    );

    if (typeof stdin === 'string' && stdin.length > 0) {
      child.stdin.write(stdin);
    }
    child.stdin.end();
  });
}

const server = http.createServer(async (req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    return sendJson(res, 200, { ok: true });
  }

  if (req.method === 'POST' && req.url === '/execute') {
    try {
      const body = await parseBody(req);
      const language = typeof body.language === 'string' ? body.language.toLowerCase() : '';
      const code = typeof body.code === 'string' ? body.code : '';
      const stdin = typeof body.stdin === 'string' ? body.stdin : '';

      if (!languageConfig[language]) {
        return sendJson(res, 400, { error: 'Unsupported language' });
      }

      if (!code || code.length > MAX_CODE_SIZE) {
        return sendJson(res, 400, { error: 'Invalid code payload' });
      }

      const result = await executeCode(language, code, stdin);
      return sendJson(res, 200, result);
    } catch (error) {
      return sendJson(res, 500, { error: error.message || 'Runner failure' });
    }
  }

  return sendJson(res, 404, { error: 'Not found' });
});

server.listen(PORT, HOST, () => {
  console.log(`Code runner listening on http://${HOST}:${PORT}`);
});
