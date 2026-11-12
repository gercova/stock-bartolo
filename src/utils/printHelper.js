/**
 * Utilidad robusta para impresión y generación/descarga de PDF
 * de comprobantes térmicos (80mm), comprobantes A4/A5 y reportes de caja.
 * Sin dependencias pesadas de terceros.
 */

export function printHtmlDocument({
  title = 'Comprobante',
  htmlContent = '',
  format = 'ticket', // 'ticket' | 'a5' | 'a4'
  customStyles = '',
}) {
  if (!htmlContent) return

  let pageSize = '80mm auto'
  let pageMargin = '2mm'
  let bodyWidth = '80mm'

  if (format === 'a5') {
    pageSize = 'A5 landscape'
    pageMargin = '8mm'
    bodyWidth = '100%'
  } else if (format === 'a4') {
    pageSize = 'A4 portrait'
    pageMargin = '10mm'
    bodyWidth = '100%'
  }

  const fullHtml = `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>${escapeXml(title)}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @page {
      size: ${pageSize};
      margin: ${pageMargin};
    }
    *, *::before, *::after {
      box-sizing: border-box;
    }
    html, body {
      margin: 0;
      padding: 0;
      background: #ffffff;
      color: #0f172a;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    body {
      padding: 4px;
      display: flex;
      justify-content: center;
    }
    .print-container {
      width: ${bodyWidth};
      max-width: ${bodyWidth};
      background: #ffffff;
    }
    @media print {
      body {
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
    }
    ${customStyles}
  </style>
</head>
<body>
  <div class="print-container">
    ${htmlContent}
  </div>
  <script>
    window.addEventListener('load', function() {
      setTimeout(function() {
        window.focus();
        window.print();
      }, 250);
    });
  </script>
</body>
</html>`

  // Intentar abrir ventana dedicada para vista previa / impresión
  const printWindow = window.open('', '_blank', 'width=800,height=850,menubar=no,toolbar=no,location=no,status=no')
  if (printWindow) {
    printWindow.document.open()
    printWindow.document.write(fullHtml)
    printWindow.document.close()
    return true
  }

  // Fallback con iframe oculto si el navegador bloquea popups
  let iframe = document.getElementById('agy-print-frame')
  if (!iframe) {
    iframe = document.createElement('iframe')
    iframe.id = 'agy-print-frame'
    iframe.style.position = 'fixed'
    iframe.style.right = '0'
    iframe.style.bottom = '0'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = '0'
    document.body.appendChild(iframe)
  }

  const iframeDoc = iframe.contentWindow?.document || iframe.contentDocument
  if (iframeDoc) {
    iframeDoc.open()
    iframeDoc.write(fullHtml)
    iframeDoc.close()
    setTimeout(() => {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
    }, 400)
    return true
  }

  // Fallback final
  window.print()
  return true
}

function escapeXml(unsafe) {
  return String(unsafe || '').replace(/[<>&'"]/g, (c) => {
    switch (c) {
      case '<': return '&lt;'
      case '>': return '&gt;'
      case '&': return '&amp;'
      case '\'': return '&apos;'
      case '"': return '&quot;'
      default: return c
    }
  })
}

