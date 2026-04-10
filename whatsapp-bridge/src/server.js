const express = require('express');
const QRCode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');

const app = express();
app.use(express.json({ limit: '1mb' }));

const BRIDGE_TOKEN = process.env.WHATSAPP_BRIDGE_TOKEN || '';
const PORT = Number(process.env.WHATSAPP_BRIDGE_PORT || 3000);
const SESSION_NAME = process.env.WHATSAPP_SESSION_NAME || 'nrsmarketing';
const MAX_TEXT_LENGTH = Number(process.env.WHATSAPP_MAX_TEXT_LENGTH || 2000);
const RATE_LIMIT_PER_MIN = Number(process.env.WHATSAPP_SENDS_PER_MINUTE || 20);

let client = null;
let sessionState = {
  status: 'idle', // idle|starting|qr|ready|disconnected|error
  qrText: null,
  qrDataUrl: null,
  lastError: null,
  lastUpdatedAt: new Date().toISOString(),
};
const sendWindow = [];

function auth(req, res, next) {
  const authHeader = req.headers.authorization || '';
  const expected = `Bearer ${BRIDGE_TOKEN}`;
  if (!BRIDGE_TOKEN || authHeader !== expected) {
    return res.status(401).json({ error: 'unauthorized' });
  }
  return next();
}

function cleanupWindow(nowMs) {
  const threshold = nowMs - 60_000;
  while (sendWindow.length > 0 && sendWindow[0] < threshold) {
    sendWindow.shift();
  }
}

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    mode: 'whatsapp-web.js',
    status: sessionState.status,
    session: SESSION_NAME,
  });
});

function touchState(patch) {
  sessionState = {
    ...sessionState,
    ...patch,
    lastUpdatedAt: new Date().toISOString(),
  };
}

async function ensureClientStarted() {
  if (client) {
    return;
  }

  touchState({ status: 'starting', lastError: null });

  client = new Client({
    authStrategy: new LocalAuth({ clientId: SESSION_NAME }),
    puppeteer: {
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
  });

  client.on('qr', async (qr) => {
    const qrDataUrl = await QRCode.toDataURL(qr);
    touchState({
      status: 'qr',
      qrText: qr,
      qrDataUrl,
    });
  });

  client.on('ready', () => {
    touchState({
      status: 'ready',
      qrText: null,
      qrDataUrl: null,
      lastError: null,
    });
  });

  client.on('auth_failure', (message) => {
    touchState({
      status: 'error',
      lastError: `auth_failure: ${message}`,
    });
  });

  client.on('disconnected', (reason) => {
    touchState({
      status: 'disconnected',
      lastError: `disconnected: ${reason}`,
    });
  });

  await client.initialize();
}

app.post('/session/start', auth, async (_req, res) => {
  try {
    await ensureClientStarted();
    return res.json({ ok: true, status: sessionState.status });
  } catch (error) {
    touchState({ status: 'error', lastError: String(error.message || error) });
    return res.status(500).json({ ok: false, error: String(error.message || error) });
  }
});

app.get('/session/status', auth, (_req, res) => {
  res.json({
    ok: true,
    ...sessionState,
    has_qr: Boolean(sessionState.qrDataUrl),
  });
});

app.get('/session/qr', auth, (_req, res) => {
  if (!sessionState.qrDataUrl) {
    return res.status(404).json({ ok: false, error: 'QR no disponible' });
  }

  return res.json({
    ok: true,
    qr_data_url: sessionState.qrDataUrl,
  });
});

app.post('/send', auth, async (req, res) => {
  const { to, text, external_id } = req.body || {};
  if (!to || !text) {
    return res.status(422).json({ error: 'to and text are required' });
  }
  if (typeof text !== 'string' || text.length > MAX_TEXT_LENGTH) {
    return res.status(422).json({ error: `text must be <= ${MAX_TEXT_LENGTH} characters` });
  }
  if (!/^\d{8,20}$/.test(String(to))) {
    return res.status(422).json({ error: 'to must be digits only and 8-20 length' });
  }

  try {
    await ensureClientStarted();

    if (sessionState.status !== 'ready') {
      return res.status(409).json({
        ok: false,
        error: `Sesion WhatsApp no lista. Estado actual: ${sessionState.status}`,
      });
    }

    const nowMs = Date.now();
    cleanupWindow(nowMs);
    if (sendWindow.length >= RATE_LIMIT_PER_MIN) {
      return res.status(429).json({
        ok: false,
        error: 'rate_limit_exceeded',
        limit_per_minute: RATE_LIMIT_PER_MIN,
      });
    }

    // Resolver JID con QueryExist (whatsapp-web.js); mejora envios con el protocolo LID de WhatsApp Web.
    const wid = await client.getNumberId(String(to));
    const fallbackCus = `${to}@c.us`;
    let chatId = fallbackCus;
    if (wid && typeof wid === 'object' && wid._serialized) {
      chatId = wid._serialized;
    } else if (typeof wid === 'string' && wid.length > 0) {
      chatId = wid;
    }

    let sent;
    try {
      sent = await client.sendMessage(chatId, text);
    } catch (firstErr) {
      if (chatId !== fallbackCus) {
        sent = await client.sendMessage(fallbackCus, text);
      } else {
        throw firstErr;
      }
    }

    sendWindow.push(nowMs);

    return res.json({
      ok: true,
      message_id: sent.id?._serialized || null,
      external_id: external_id || null,
      to,
      chat_id: chatId,
      status: sessionState.status,
    });
  } catch (error) {
    const msg = String(error.message || error);
    const lidHint =
      /no lid|lid for user|lid-migrated|accountlid/i.test(msg)
        ? ' Sugerencia: abri una vez el chat con ese contacto en WhatsApp Web (la misma sesion vinculada al bridge), o probá wa.me/<numero> en el navegador, y reintentá el envio.'
        : '';
    return res.status(500).json({
      ok: false,
      error: msg + lidHint,
    });
  }
});

app.listen(PORT, '127.0.0.1', () => {
  console.log(`WhatsApp bridge listening on http://127.0.0.1:${PORT}`);
});
