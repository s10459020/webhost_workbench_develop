(() => {
  const endpoints = {
    get_path_type: 'site/get_path_type.php',
    get_php_info: 'site/get_php_info.php',
    get_root: 'site/get_root.php',
    cmd_exec: 'site/cmd_exec.php',

    create_dir: 'dir/create_dir.php',
    delete_dir: 'dir/delete_dir.php',
    download_dir: 'dir/download_dir.php',
    download_files: 'dir/download_files.php',
    download_items: 'dir/download_items.php',
    rename_dir: 'dir/rename_dir.php',
    scan_all_file: 'dir/scan_all_file.php',
    scan_dir: 'dir/scan_dir.php',

    create_file: 'file/create_file.php',
    delete_file: 'file/delete_file.php',
    download_file: 'file/download_file.php',
    preview_file: 'file/preview_file.php',
    read_file: 'file/read_file.php',
    rename_file: 'file/rename_file.php',
    scan_file: 'file/scan_file.php',
    upload_files: 'file/upload_files.php',
    write_file: 'file/write_file.php',

    mysql_ping: 'db/mysql_ping.php',
    mysql_schema: 'db/mysql_schema.php',
    mysql_query: 'db/mysql_query.php',
    mysql_export: 'db/mysql_export.php',

    ftp_list: 'ftp/list.php',
    ftp_probe: 'ftp/probe.php',
    ftp_download: 'ftp/download.php',
    ftp_upload: 'ftp/upload.php',
    ftp_delete: 'ftp/delete.php',
    ftp_mkdir: 'ftp/mkdir.php'
  };

  const endpointMethods = {
    get_path_type: 'POST', get_php_info: 'GET', get_root: 'POST', cmd_exec: 'POST',
    create_dir: 'POST', delete_dir: 'POST', download_dir: 'POST', download_files: 'POST',
    download_items: 'POST', rename_dir: 'POST', scan_all_file: 'POST', scan_dir: 'POST',
    create_file: 'POST', delete_file: 'POST', download_file: 'POST', preview_file: 'GET',
    read_file: 'POST', rename_file: 'POST', scan_file: 'POST', upload_files: 'POST', write_file: 'POST',
    mysql_ping: 'POST', mysql_schema: 'POST', mysql_query: 'POST', mysql_export: 'POST',
    ftp_list: 'POST', ftp_probe: 'POST', ftp_download: 'POST', ftp_upload: 'POST', ftp_delete: 'POST', ftp_mkdir: 'POST'
  };

  const downloadEndpoints = new Set(['download_dir', 'download_files', 'download_items', 'download_file', 'mysql_export', 'ftp_download']);
  let selectedServer = null;
  let readyTask = null;
  let runtimeInfo = null;

  class ApiCallError extends Error {
    constructor(message, meta = {}) { super(message); this.name = 'ApiCallError'; Object.assign(this, meta); }
  }

  function endpoint(name) {
    const key = String(name || '').replace(/^\/+/, '');
    if (!key) throw new Error('api endpoint is required');
    return endpoints[key] || key;
  }

  function endpointMethod(name, fallback = 'POST') {
    const key = String(name || '').replace(/^\/+/, '');
    return endpointMethods[key] || String(fallback).toUpperCase();
  }

  function mergeParams(args) {
    if (args.length === 1 && args[0] instanceof FormData) return args[0];
    return Object.assign({}, ...args.filter((x) => x && !(x instanceof FormData)));
  }

  function bodyFrom(params) {
    if (params instanceof FormData) return params;
    const body = new URLSearchParams();
    Object.keys(params || {}).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null) body.append(key, String(params[key]));
    });
    return body;
  }

  async function ensureReady(options = {}) {
    if (!readyTask || options.reload) {
      readyTask = window.SHV.loadServerConfig(options).then((cfg) => {
        selectedServer = cfg.active;
        return selectedServer;
      });
    }
    return readyTask;
  }

  async function getRuntimeInfo(options = {}) {
    await ensureReady(options);
    if (!runtimeInfo || options.reload) runtimeInfo = JSON.parse(await request('GET', 'get_php_info', {}) || '{}');
    return runtimeInfo;
  }

  async function getOsFamily(options = {}) {
    const info = await getRuntimeInfo(options);
    return String(info?.php?.os_family || info?.php?.os || '').toLowerCase();
  }

  function getBase() {
    if (!selectedServer) throw new Error('ManagerApi not ready');
    return new URL('api/router.php', window.SHV_MANAGER_URL).toString();
  }

  function url(path) {
    const p = endpoint(path);
    return `${getBase()}?__endpoint=${encodeURIComponent(p)}`;
  }

  function submit(path, data = {}, options = {}) {
    const method = String(options.method || endpointMethod(path, 'POST')).toUpperCase();
    const target = String(options.target || '_self');
    const action = getBase();
    const form = document.createElement('form');
    form.method = method;
    form.action = action;
    form.target = target;
    form.style.display = 'none';

    const e = document.createElement('input');
    e.type = 'hidden';
    e.name = '__endpoint';
    e.value = endpoint(path);
    form.appendChild(e);

    Object.keys(data || {}).forEach((k) => {
      if (data[k] === undefined || data[k] === null) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = k;
      input.value = String(data[k]);
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
    return true;
  }

  function showError(err, title = 'API call failed') {
    const status = Number(err?.status || 0);
    const statusText = String(err?.statusText || '').trim();
    const method = String(err?.method || '').trim();
    const targetUrl = String(err?.url || '').trim();
    const body = String(err?.body || err?.message || '').trim();
    const lines = [title, status > 0 ? `status: ${status}${statusText ? ` ${statusText}` : ''}` : 'status: network error or invalid response'];
    if (method || targetUrl) lines.push(`request: ${method || 'REQUEST'} ${targetUrl || ''}`.trim());
    if (body) lines.push(`message: ${body}`);
    const text = lines.join('\n');
    console.error(text, err);
    return text;
  }


  async function request(method, pathOrUrl, ...params) {
    await ensureReady();
    const data = mergeParams(params);
    const isUrl = /^(https?:)?\/\//i.test(pathOrUrl) || String(pathOrUrl || '').startsWith('/');
    const target = isUrl ? String(pathOrUrl) : getBase();
    const finalData = (data instanceof FormData) ? data : Object.assign({}, data, isUrl ? {} : { __endpoint: endpoint(pathOrUrl) });
    if (finalData instanceof FormData && !isUrl) finalData.set('__endpoint', endpoint(pathOrUrl));
    const query = bodyFrom(finalData).toString();
    const requestUrl = method === 'GET' ? `${target}${target.includes('?') ? '&' : '?'}${query}` : target;
    const options = method === 'GET' ? { method } : { method, body: bodyFrom(finalData) };
    let res;
    try { res = await fetch(requestUrl, options); }
    catch (cause) {
      throw new ApiCallError(String(cause?.message || 'network error'), { status: 0, statusText: 'NETWORK_ERROR', method, url: requestUrl, cause });
    }
    const text = await res.text();
    if (!res.ok) throw new ApiCallError(`${res.status} ${res.statusText}: ${text}`, { status: res.status, statusText: res.statusText, method, url: requestUrl, body: text });
    return text;
  }

  function fileNameFromContentDisposition(headerValue) {
    const raw = String(headerValue || '');
    const utf8Match = raw.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match && utf8Match[1]) { try { return decodeURIComponent(utf8Match[1]); } catch (_) {} }
    const plainMatch = raw.match(/filename="?([^";]+)"?/i);
    return plainMatch && plainMatch[1] ? plainMatch[1] : '';
  }

  async function download(downloadUrl, fallbackName = 'download.bin') {
    const res = await fetch(downloadUrl, { method: 'POST' });
    if (!res.ok) {
      const body = await res.text();
      showError(new ApiCallError(body || `${res.status} ${res.statusText}`, { status: res.status, statusText: res.statusText, method: 'POST', url: downloadUrl, body }), 'Download failed');
      return false;
    }
    const blob = await res.blob();
    const fileName = fileNameFromContentDisposition(res.headers.get('Content-Disposition')) || fallbackName;
    const blobUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(blobUrl);
    return true;
  }

  window.ManagerApi = {
    endpoints,
    endpoint,
    ready: ensureReady,
    url,
    getBase,
    showError,
    ApiCallError,
    download,
    post: (path, data = {}) => request('POST', path, data),
    get: (path, data = {}) => request('GET', path, data),
    submit,
    call: (path, data = {}, method = '') => {
      const key = String(path || '').replace(/^\/+/, '');
      if (downloadEndpoints.has(key)) return ensureReady().then(() => (submit(key, data, { method: endpointMethod(key, 'POST') }), ''));
      return request(String(method || endpointMethod(key, 'POST')).toUpperCase(), path, data);
    },
    runtimeInfo: getRuntimeInfo,
    osFamily: getOsFamily,
    callByOs: async (map, data = {}) => {
      const os = await getOsFamily();
      const key = (os.includes('windows') ? map?.windows : map?.linux) || map?.default || '';
      if (!key) throw new Error(`no api mapping for os=${os}`);
      return window.ManagerApi.call(key, data);
    }
  };

  window.api = window.ManagerApi.call;
})();
