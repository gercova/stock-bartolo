import { fileURLToPath, URL } from 'node:url'
import { spawn } from 'node:child_process'
import net from 'node:net'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

const apiTarget = process.env.VITE_API_TARGET || 'http://127.0.0.1:8000'
const rootDir = fileURLToPath(new URL('.', import.meta.url))

function isPortListening(host, port, timeoutMs = 250) {
  return new Promise((resolve) => {
    const socket = net.createConnection({ host, port })
    let settled = false
    const done = (result) => {
      if (!settled) {
        settled = true
        socket.destroy()
        resolve(result)
      }
    }
    socket.setTimeout(timeoutMs)
    socket.once('connect', () => done(true))
    socket.once('timeout', () => done(false))
    socket.once('error', () => done(false))
  })
}

async function ensureLocalPhpServer() {
  if (process.env.VITE_API_TARGET) return

  let parsed
  try {
    parsed = new URL(apiTarget)
  } catch {
    return
  }

  const host = parsed.hostname || '127.0.0.1'
  const port = Number(parsed.port || 8000)

  if (await isPortListening(host, port)) {
    return
  }

  const existing = globalThis.__stockBartoloPhpProc
  if (existing && !existing.killed && existing.exitCode === null) {
    return
  }

  const child = spawn('php', ['-S', `${host}:${port}`, '-t', rootDir], {
    cwd: rootDir,
    stdio: ['ignore', 'ignore', 'pipe'],
  })

  globalThis.__stockBartoloPhpProc = child

  child.stderr?.on('data', (chunk) => {
    const msg = chunk.toString()
    if (/Fatal error|Parse error|Failed to listen/i.test(msg)) {
      console.error(`[php-backend] ${msg.trim()}`)
    }
  })

  child.once('error', (err) => {
    console.error(`[php-backend] No se pudo iniciar php -S ${host}:${port}: ${err.message}`)
  })

  child.once('exit', () => {
    if (globalThis.__stockBartoloPhpProc === child) {
      globalThis.__stockBartoloPhpProc = null
    }
  })

  if (!globalThis.__stockBartoloPhpExitHooked) {
    globalThis.__stockBartoloPhpExitHooked = true
    process.once('exit', () => {
      const proc = globalThis.__stockBartoloPhpProc
      if (proc && !proc.killed && proc.exitCode === null) {
        try {
          proc.kill('SIGTERM')
        } catch {
          // ignore cleanup errors
        }
      }
    })
  }

  const deadline = Date.now() + 2500
  while (Date.now() < deadline) {
    if (child.exitCode !== null) break
    if (await isPortListening(host, port, 150)) {
      console.log(`[php-backend] Servidor PHP iniciado automáticamente en http://${host}:${port}`)
      break
    }
    await new Promise((r) => setTimeout(r, 80))
  }
}

function attachPhpBackendHooks(server) {
  const readyPromise = ensureLocalPhpServer()
  server.middlewares.use(async (req, _res, next) => {
    if (req.url && (req.url.startsWith('/php_action') || req.url.startsWith('/assests'))) {
      await readyPromise
    }
    next()
  })
  server.httpServer?.once('close', () => {
    const proc = globalThis.__stockBartoloPhpProc
    if (proc && !proc.killed && proc.exitCode === null) {
      try {
        proc.kill('SIGTERM')
      } catch {
        // ignore cleanup errors
      }
      globalThis.__stockBartoloPhpProc = null
    }
  })
}

function phpDevBackendPlugin() {
  return {
    name: 'stock-bartolo-php-backend',
    configureServer(server) {
      attachPhpBackendHooks(server)
    },
    configurePreviewServer(server) {
      attachPhpBackendHooks(server)
    },
  }
}

function buildProxyConfig() {
  const configure = (proxy) => {
    proxy.on('error', (err, _req, res) => {
      if (res && !res.headersSent && typeof res.writeHead === 'function') {
        res.writeHead(502, { 'Content-Type': 'application/json; charset=utf-8' })
        res.end(
          JSON.stringify({
            success: false,
            code: 502,
            messages: `No se pudo conectar con el servidor backend PHP (${apiTarget}). Asegúrate de que PHP esté disponible o ejecuta "npm run serve:api".`,
            details: { reason: 'proxy_upstream_unreachable', code: err?.code || 'ECONNREFUSED' },
          })
        )
      }
    })
  }

  return {
    '/php_action': {
      target: apiTarget,
      changeOrigin: true,
      configure,
    },
    '/assests': {
      target: apiTarget,
      changeOrigin: true,
      configure,
    },
  }
}

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    phpDevBackendPlugin(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: buildProxyConfig(),
  },
  preview: {
    port: 4173,
    proxy: buildProxyConfig(),
  },
})
