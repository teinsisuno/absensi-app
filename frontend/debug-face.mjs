// Debug /setup/face — tangkap console + exception via CDP
const CDP = 'http://127.0.0.1:9222'
const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

const list = await fetch(`${CDP}/json`).then((r) => r.json())
const page = list.find((t) => t.type === 'page')
if (!page) throw new Error('Tidak ada tab page di CDP')
const ws = new WebSocket(page.webSocketDebuggerUrl)
await new Promise((res, rej) => {
  ws.onopen = res
  ws.onerror = rej
})

let id = 0
const pending = new Map()
ws.onmessage = (ev) => {
  const m = JSON.parse(ev.data)
  if (m.id && pending.has(m.id)) {
    pending.get(m.id)(m)
    pending.delete(m.id)
  } else if (m.method === 'Runtime.consoleAPICalled') {
    console.log('[console]', m.params.args.map((a) => a.value ?? a.description ?? '').join(' '))
  } else if (m.method === 'Runtime.exceptionThrown') {
    const d = m.params.exceptionDetails
    console.log('[EXCEPTION]', d.text, d.exception?.description?.slice(0, 500) ?? '')
  }
}

const send = (method, params = {}) =>
  new Promise((res) => {
    const mid = ++id
    pending.set(mid, res)
    ws.send(JSON.stringify({ id: mid, method, params }))
  })
const evalJs = async (expression) => {
  const res = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })
  if (res.result?.exceptionDetails) {
    console.log('[EVAL-EXC]', res.result.exceptionDetails.text, res.result.exceptionDetails.exception?.description?.slice(0, 300))
    return undefined
  }
  return res.result?.result?.value
}

await send('Runtime.enable')
await send('Log.enable')
await evalJs(`location.href = 'http://toko-uji-absensi.test:3000/setup/face'`)
await sleep(8000)
console.log('URL:', await evalJs('location.pathname'))
console.log('BODY:', (await evalJs('document.body.innerText'))?.slice(0, 600))
console.log('video el?', await evalJs('document.querySelector("video") !== null'))
ws.close()
process.exit(0)
