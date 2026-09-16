(() => {
  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  // Lightweight markdown renderer for headings, lists, links, code, and paragraphs.
  function renderToHtml(markdownText) {
    const lines = String(markdownText || '').replace(/\r/g, '').split('\n');
    const out = [];
    let inUl = false;
    let inCode = false;

    const closeUl = () => {
      if (inUl) {
        out.push('</ul>');
        inUl = false;
      }
    };

    for (const raw of lines) {
      const line = raw;
      if (line.startsWith('```')) {
        closeUl();
        if (!inCode) {
          out.push('<pre><code>');
          inCode = true;
        } else {
          out.push('</code></pre>');
          inCode = false;
        }
        continue;
      }

      if (inCode) {
        out.push(escapeHtml(line) + '\n');
        continue;
      }

      const h = line.match(/^(#{1,6})\s+(.*)$/);
      if (h) {
        closeUl();
        const level = h[1].length;
        out.push(`<h${level}>${inlineMd(h[2])}</h${level}>`);
        continue;
      }

      const li = line.match(/^[-*]\s+(.*)$/);
      if (li) {
        if (!inUl) {
          out.push('<ul>');
          inUl = true;
        }
        out.push(`<li>${inlineMd(li[1])}</li>`);
        continue;
      }

      if (line.trim() === '') {
        closeUl();
        continue;
      }

      closeUl();
      out.push(`<p>${inlineMd(line)}</p>`);
    }

    closeUl();
    if (inCode) out.push('</code></pre>');
    return out.join('\n');
  }

  function inlineMd(text) {
    let s = escapeHtml(text);
    s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    return s;
  }

  function renderToElement(el, markdownText) {
    if (!el) return;
    el.innerHTML = renderToHtml(markdownText);
  }

  window.MarkdownPreview = {
    renderToHtml,
    renderToElement
  };
})();
