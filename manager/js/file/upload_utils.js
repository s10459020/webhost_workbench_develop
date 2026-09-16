(() => {
  function pickFilesMulti() {
    return new Promise((resolve) => {
      const input = document.createElement("input");
      input.type = "file";
      input.multiple = true;
      input.onchange = () => resolve(Array.from(input.files || []));
      input.click();
    });
  }

  function pickSingleFolder() {
    return new Promise((resolve) => {
      const input = document.createElement("input");
      input.type = "file";
      input.webkitdirectory = true;
      input.multiple = false;
      input.onchange = () => resolve(Array.from(input.files || []));
      input.click();
    });
  }

  function dirname(path) {
    const p = String(path || "").replace(/\\/g, "/");
    const i = p.lastIndexOf("/");
    return i >= 0 ? p.slice(0, i) : "";
  }

  async function collectFromDragItems(items, baseDir) {
    const entries = [];
    for (let i = 0; i < (items?.length || 0); i++) {
      const entry = items[i].webkitGetAsEntry?.();
      if (entry) entries.push(entry);
    }
    return await collectFromEntries(entries, baseDir || "");
  }

  async function collectFromEntries(entries, dir = "") {
    let files = [];
    let dirs = [];
    for (let i = 0; i < entries.length; i++) {
      const entry = entries[i];
      if (entry.isFile) {
        await new Promise((resolve) => {
          entry.file((file) => {
            files.push(file);
            dirs.push(dir);
            resolve();
          });
        });
      } else if (entry.isDirectory) {
        await new Promise((resolve) => {
          entry.createReader().readEntries(async (sub) => {
            const res = await collectFromEntries(sub, `${dir}/${entry.name}`);
            files = files.concat(res.files);
            dirs = dirs.concat(res.dirs);
            resolve();
          });
        });
      }
    }
    return { files, dirs };
  }

  function dirsFromFolderFiles(folderFiles, targetDir) {
    return (folderFiles || []).map((f) => {
      const relDir = dirname(f.webkitRelativePath || "");
      return relDir ? `${targetDir}/${relDir}` : targetDir;
    });
  }

  function dirsFromFiles(files, targetDir) {
    return (files || []).map(() => targetDir);
  }

  window.UploadLib = {
    pickFilesMulti,
    pickSingleFolder,
    collectFromDragItems,
    dirsFromFolderFiles,
    dirsFromFiles
  };
})();

