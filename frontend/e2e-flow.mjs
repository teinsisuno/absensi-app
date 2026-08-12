// E2E flow test — splash → register → set-pin → setup (kode unik + face) → dashboard
// Jalankan saat Chrome headless remote-debugging-port=9222 aktif.
const CDP = 'http://127.0.0.1:9222'
const BASE = 'http://toko-uji-absensi.test:3000'
const EMAIL = `e2e-${Date.now()}@example.com`

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

async function getPage() {
  const list = await fetch(`${CDP}/json`).then((r) => r.json())
  return list.find((t) => t.type === 'page')
}

async function connect() {
  const page = await getPage()
  const ws = new WebSocket(page.webSocketDebuggerUrl)
  await new Promise((resolve, reject) => {
    ws.onopen = resolve
    ws.onerror = reject
  })
  let id = 0
  const pending = new Map()
  ws.onmessage = (ev) => {
    const msg = JSON.parse(ev.data)
    if (msg.id && pending.has(msg.id)) {
      pending.get(msg.id)(msg)
      pending.delete(msg.id)
    }
  }
  const send = (method, params = {}) =>
    new Promise((resolve) => {
      const mid = ++id
      pending.set(mid, resolve)
      ws.send(JSON.stringify({ id: mid, method, params }))
    })
  const evalJs = async (expression) => {
    const res = await send('Runtime.evaluate', {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })
    if (res.result?.exceptionDetails) throw new Error('JS error: ' + JSON.stringify(res.result.exceptionDetails))
    return res.result?.result?.value
  }
  const waitFor = async (expr, label, timeout = 20000) => {
    const start = Date.now()
    while (Date.now() - start < timeout) {
      const ok = await evalJs(expr)
      if (ok) return true
      await sleep(500)
    }
    throw new Error(`TIMEOUT menunggu: ${label}`)
  }
  return { ws, send, evalJs, waitFor }
}

async function setInput(ev, selector, value) {
  await ev.evalJs(`(() => {
    const el = document.querySelector(${JSON.stringify(selector)})
    if (!el) return false
    el.focus()
    el.value = ${JSON.stringify(value)}
    el.dispatchEvent(new Event('input', { bubbles: true }))
    el.dispatchEvent(new Event('change', { bubbles: true }))
    return true
  })()`)
}

async function submitForm(ev, selector = 'form') {
  await ev.evalJs(`(() => {
    const f = document.querySelector(${JSON.stringify(selector)})
    if (!f) return false
    f.requestSubmit()
    return true
  })()`)
}

const ev = await connect()
const results = []

// Reset state (localStorage) biar tiap run mulai dari nol — ala perangkat baru
await ev.evalJs(`location.href = ${JSON.stringify(BASE)}`)
await new Promise((r) => setTimeout(r, 1500))
await ev.evalJs(`localStorage.clear(); sessionStorage.clear(); true`)
await new Promise((r) => setTimeout(r, 500))

async function step(name, fn) {
  try {
    await fn()
    results.push(`✅ ${name}`)
  } catch (e) {
    results.push(`❌ ${name} — ${e.message}`)
  }
}

// 1. Splash → redirect ke /register (belum pernah daftar)
await step('Splash → register', async () => {
  await ev.evalJs(`location.href = ${JSON.stringify(BASE + '/splash')}`)
  await ev.waitFor(`location.pathname === '/register'`, 'redirect ke /register', 25000)
  const hasForm = await ev.evalJs(`document.body.innerText.includes('Daftar Akun')`)
  if (!hasForm) throw new Error('Form Daftar Akun tidak muncul')
})

// 2. Register
await step('Register', async () => {
  await setInput(ev, '#name', 'E2E User')
  await setInput(ev, '#email', EMAIL)
  await setInput(ev, '#password', 'password123')
  await submitForm(ev)
  await ev.waitFor(`location.pathname === '/set-pin'`, 'redirect ke /set-pin', 20000)
  const hasPin = await ev.evalJs(`document.body.innerText.includes('Atur PIN')`)
  if (!hasPin) throw new Error('Halaman Atur PIN tidak muncul')
})

// 3. Set PIN
await step('Set PIN', async () => {
  await setInput(ev, '#pin', '2468')
  await setInput(ev, '#pin-confirm', '2468')
  await submitForm(ev)
  await ev.waitFor(`location.pathname === '/setup'`, 'redirect ke /setup', 20000)
  const hasSetup = await ev.evalJs(`document.body.innerText.includes('Pengaturan Awal')`)
  if (!hasSetup) throw new Error('Halaman Pengaturan Awal tidak muncul')
})

// 4. Kode unik → nama karyawan muncul otomatis
await step('Kode unik → nama muncul', async () => {
  await setInput(ev, '#code', 'TEST5678')
  await ev.waitFor(`document.body.innerText.includes('Cici Test')`, 'nama Cici Test muncul', 15000)
  const valid = await ev.evalJs(`document.body.innerText.includes('Kode valid')`)
  if (!valid) throw new Error('Badge Kode valid tidak muncul')
})

// 5. Scan wajah → mode demo → kembali
await step('Scan wajah (demo) → kembali', async () => {
  // klik tombol Scan Wajah
  await ev.evalJs(`(() => {
    const btn = [...document.querySelectorAll('button')].find(b => b.innerText.includes('Scan Wajah'))
    if (!btn) return false
    btn.click()
    return true
  })()`)
  await ev.waitFor(`location.pathname === '/setup/face'`, 'redirect ke /setup/face', 15000)
  // kamera gagal di headless → tombol demo muncul
  const demoBtn = await ev.waitFor(
    `[...document.querySelectorAll('button')].some(b => b.innerText.includes('Lanjut tanpa kamera'))`,
    'tombol lanjut tanpa kamera',
    15000,
  )
  await ev.evalJs(`(() => {
    const btn = [...document.querySelectorAll('button')].find(b => b.innerText.includes('Lanjut tanpa kamera'))
    if (!btn) return false
    btn.click()
    return true
  })()`)
  await ev.waitFor(`location.pathname === '/setup'`, 'kembali ke /setup', 15000)
  const faceDone = await ev.evalJs(`document.body.innerText.includes('Wajah sudah discan')`)
  if (!faceDone) throw new Error('Status wajah sudah discan tidak muncul')
})

// 6. Simpan → dashboard
await step('Simpan → dashboard', async () => {
  const saveEnabled = await ev.evalJs(`(() => {
    const btn = [...document.querySelectorAll('button')].find(b => b.innerText.trim() === 'Simpan')
    return btn ? !btn.disabled : false
  })()`)
  if (!saveEnabled) throw new Error('Tombol Simpan belum aktif')
  await ev.evalJs(`(() => {
    const btn = [...document.querySelectorAll('button')].find(b => b.innerText.trim() === 'Simpan')
    if (!btn) return false
    btn.click()
    return true
  })()`)
  await ev.waitFor(`location.pathname === '/dashboard'`, 'redirect ke /dashboard', 20000)
  const dash = await ev.evalJs(`document.body.innerText.includes('Selamat datang')`)
  if (!dash) throw new Error('Dashboard tidak muncul')
  const name = await ev.evalJs(`document.body.innerText.includes('Cici Test')`)
  if (!name) throw new Error('Nama karyawan tidak muncul di dashboard')
})

console.log('\n===== HASIL E2E FLOW =====')
results.forEach((r) => console.log(r))
const failed = results.filter((r) => r.startsWith('❌'))
console.log(failed.length ? `\n${failed.length} GAGAL` : '\nSEMUA LULUS ✔')
console.log('EMAIL dipakai:', EMAIL)
ev.ws.close()
process.exit(failed.length ? 1 : 0)
