export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
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

export function getCookie(name) {
  if (typeof document === 'undefined') return ''
  const match = document.cookie.match(
    new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)')
  )
  return match ? decodeURIComponent(match[1]) : ''
}
