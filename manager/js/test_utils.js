(() => {
  async function ensureApiReady() {
    if (window.ManagerApi && typeof window.ManagerApi.ready === "function") {
      await window.ManagerApi.ready();
    }
  }
  const utils = {
    resolveValue(v, ctx) {
      if (typeof v !== "string") return v;
      if (v === "$createdPath") return ctx.createdPath;
      if (v === "$renamedPath") return ctx.renamedPath;
      if (v === "$lastScan") return ctx.lastScan;
      if (v === "$lastRead") return ctx.lastRead;
      if (v === "$lastResponse") return ctx.lastResponse;
      return v;
    },
    basename(path) {
      if (!path) return "";
      const parts = String(path).replace(/\\/g, "/").split("/");
      return parts[parts.length - 1] || "";
    },
    async postForm(url, data) {
      await ensureApiReady();
      const body = new URLSearchParams();
      Object.keys(data || {}).forEach((k) => {
        if (data[k] !== undefined && data[k] !== null) body.append(k, String(data[k]));
      });
      const res = await fetch(url, { method: "POST", body });
      const text = await res.text();
      if (!res.ok) {
        console.error("test postForm failed", { url, data, status: res.status, statusText: res.statusText, responseText: text });
        throw new Error(`${res.status} ${res.statusText}: ${text}`);
      }
      return text;
    },
    async postMultipart(url, files, dirs) {
      await ensureApiReady();
      const fd = new FormData();
      (files || []).forEach((f) => fd.append("files[]", f));
      (dirs || []).forEach((d) => fd.append("dirs[]", String(d)));
      const res = await fetch(url, { method: "POST", body: fd });
      const text = await res.text();
      if (!res.ok) {
        console.error("test postMultipart failed", { url, status: res.status, statusText: res.statusText, responseText: text });
        throw new Error(`${res.status} ${res.statusText}: ${text}`);
      }
      return text;
    },
    async downloadByPost(url, data, filenameHint) {
      await ensureApiReady();
      const body = new URLSearchParams();
      Object.keys(data || {}).forEach((k) => {
        if (data[k] !== undefined && data[k] !== null) body.append(k, String(data[k]));
      });
      const res = await fetch(url, { method: "POST", body });
      if (!res.ok) {
        const text = await res.text();
        console.error("test downloadByPost failed", { url, data, status: res.status, statusText: res.statusText, responseText: text });
        throw new Error(`${res.status} ${res.statusText}: ${text}`);
      }
      const blob = await res.blob();
      let filename = filenameHint || "download.bin";
      const dispo = res.headers.get("content-disposition") || "";
      const m = dispo.match(/filename=\"?([^\";]+)\"?/i);
      if (m && m[1]) filename = m[1];
      const link = document.createElement("a");
      const urlObj = URL.createObjectURL(blob);
      link.href = urlObj;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(urlObj);
      return filename;
    },
    async runScriptText(scriptText, env) {
      const AsyncFunction = Object.getPrototypeOf(async function () {}).constructor;
      const sharedScope = env.scope || {};
      const localScope = Object.create(sharedScope);
      localScope.api = env.api;
      localScope.utils = env.utils;
      localScope.ctx = env.ctx;
      localScope.log = env.log;
      localScope.assert = env.assert;
      if (env.expose && typeof env.expose === "object") {
        Object.keys(env.expose).forEach((k) => { localScope[k] = env.expose[k]; });
      }
      const sandbox = new Proxy(localScope, {
        has(target, prop) { return prop in target; },
        get(target, prop) { return target[prop]; },
        set(target, prop, value) { target[prop] = value; return true; }
      });
      const runner = new AsyncFunction(
        "api",
        "utils",
        "ctx",
        "scope",
        "sandbox",
        "log",
        "assert",
        `with (sandbox) { ${scriptText} }`
      );
      return await runner(
        env.api,
        env.utils,
        env.ctx,
        sharedScope,
        sandbox,
        env.log,
        env.assert
      );
    }
  };
  window.TestApi = { utils };
})();
