(() => {
  const MANAGER_URL = new URL('../', document.currentScript.src);
  const ACTIVE_FILE_URL = new URL('config/server.json', MANAGER_URL);
  const SERVER_CONFIG = {
    allowedVersions: [
      { version: 'PHP 7.4.33', apiDir: 'api/php74', apiVersion: 'php74' },
      { version: 'PHP 8.4.20', apiDir: 'api/php84', apiVersion: 'php84' }
    ]
  };

  let statePromise = null;
  let state = null;

  function findVersion(value) {
    return SERVER_CONFIG.allowedVersions.find((x) => x.version === value || x.apiDir === value || x.apiVersion === value);
  }

  async function readActiveEntryFromFile() {
    const res = await fetch(ACTIVE_FILE_URL.toString(), { cache: 'no-store' });
    if (!res.ok) throw new Error(`cannot read ${ACTIVE_FILE_URL.pathname}: ${res.status} ${res.statusText}`);
    const json = await res.json();
    return findVersion(json?.api_version) || null;
  }

  function publish(nextState) {
    state = nextState;
    window.SHV_SERVER_CONFIG = nextState;
    window.SHV_SERVER_VERSION = nextState.active?.version || '';
    window.SHV_API_DIR = nextState.active?.apiDir || '';
    window.SHV_MANAGER_URL = MANAGER_URL;
    return nextState;
  }

  async function loadServerConfig(options = {}) {
    if (statePromise && !options.reload) return statePromise;
    statePromise = Promise.resolve().then(async () => {
      const active = await readActiveEntryFromFile();
      return publish({ active, allowedVersions: SERVER_CONFIG.allowedVersions });
    });
    return statePromise;
  }

  async function getActiveServer() {
    return (state || await loadServerConfig()).active;
  }

  async function setActiveServer(version) {
    const target = findVersion(version);
    const writerApi = `${MANAGER_URL}${target.apiDir}/site/set_active_server.php`;
    const body = new URLSearchParams();
    body.set('api_version', target.apiVersion);
    const res = await fetch(writerApi, { method: 'POST', body });
    const text = await res.text();
    if (!res.ok) throw new Error(text || `${res.status} ${res.statusText}`);

    await loadServerConfig({ reload: true });
    return state;
  }

  window.SHV = {
    loadServerConfig,
    getActiveServer,
    setActiveServer
  };
})();
