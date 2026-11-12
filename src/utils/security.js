export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

export function sanitizeInput(value) {
  if (value === null || value === undefined) return ''
  return String(value)
    .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '')
    .trim()
}

export function sanitizeObject(obj) {
  if (typeof obj !== 'object' || obj === null) return obj
  if (Array.isArray(obj)) {
    return obj.map(item => sanitizeObject(item))
  }
  const result = {}
  for (const [key, val] of Object.entries(obj)) {
    if (typeof val === 'string') {
      result[key] = sanitizeInput(val)
    } else if (typeof val === 'object' && val !== null) {
      result[key] = sanitizeObject(val)
    } else {
      result[key] = val
    }
  }
  return result
}

const DANGEROUS_PATTERNS = [
  /<\s*\/?\s*script\b/i,
  /<\?(?:php|=|\s)/i,
  /<\s*(?:iframe|object|embed|applet|meta|base)\b/i,
  /<\s*svg\b[^>]*\bon[a-z]+\s*=/i,
  /(?:javascript|vbscript)\s*:/i,
  /data\s*:\s*text\/html/i,
  /\bon(?:abort|blur|change|click|dblclick|error|focus|input|keydown|keypress|keyup|load|mousedown|mouseenter|mouseleave|mousemove|mouseout|mouseover|mouseup|reset|resize|select|submit|unload)\s*=/i,
  /\bunion\s+(?:all\s+)?select\b/i,
  /\binto\s+(?:outfile|dumpfile)\b/i,
  /\b(?:sleep|benchmark)\s*\(/i,
  /\.\.\/|\.\.\\/i,
  /;\s*(?:cat|ls|rm|chmod|chown|wget|curl|bash|sh|nc|python|perl)\b/i,
  /(?:file|gopher|dict|phar|ldap|expect):\/\//i,
]

export function hasSuspiciousInput(value) {
  const str = String(value ?? '')
  if (str.includes('\0')) return true
  return DANGEROUS_PATTERNS.some((pattern) => pattern.test(str))
}

export function findUnsafeField(fieldsMap) {
  for (const [label, value] of Object.entries(fieldsMap)) {
    if (hasSuspiciousInput(value)) {
      return label
    }
  }
  return null
}

export function isValidEmail(email) {
  if (!email || typeof email !== 'string') return false
  return /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/.test(
    email.trim()
  )
}

export function isValidPhone(phone) {
  if (!phone || typeof phone !== 'string') return false
  return /^[+()0-9\s-]{6,25}$/.test(phone.trim())
}

export function isValidDocNumber(type, number) {
  const clean = String(number ?? '').trim()
  switch (String(type).toUpperCase()) {
    case 'DNI':
      return /^\d{8}$/.test(clean)
    case 'RUC':
      return /^\d{11}$/.test(clean)
    case 'CE':
      return /^[a-zA-Z0-9]{8,12}$/.test(clean)
    case 'PASAPORTE':
      return /^[a-zA-Z0-9]{6,15}$/.test(clean)
    default:
      return clean.length >= 3 && clean.length <= 25
  }
}

export function getCookie(name) {
  if (typeof document === 'undefined') return ''
  const match = document.cookie.match(
    new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)')
  )
  return match ? decodeURIComponent(match[1]) : ''
}
