/**
 * Autocomplete de personajes: input visible + hidden id.
 * Resuelve coincidencia exacta al pulsar Enter, al perder foco o en submit.
 */
(function (global) {
  "use strict";

  var registry = new WeakMap();

  function parseCharacters(data) {
    if (!data || !data.ok) return [];
    if (Array.isArray(data.data)) return data.data;
    if (data.data && Array.isArray(data.data.characters)) return data.data.characters;
    if (Array.isArray(data.characters)) return data.characters;
    return [];
  }

  function fetchCharacters(state, q) {
    var sep = state.fetchUrl.indexOf("?") >= 0 ? "&" : "?";
    var url = state.fetchUrl + sep + "q=" + encodeURIComponent(q);
    return fetch(url, { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(parseCharacters)
      .catch(function () { return []; });
  }

  function pickItem(container, id, name) {
    var state = registry.get(container);
    if (!state) return;
    state.input.value = name;
    state.hidden.value = String(id);
    state.selectedName = name;
    state.results.classList.remove("is-open");
    state.results.style.display = "none";
    state.results.innerHTML = "";
    state.lastResults = [];
    if (typeof state.onSelect === "function") {
      state.onSelect(String(id), name, container);
    }
  }

  function tryAutoPick(container, items, query) {
    var q = query.trim().toLowerCase();
    if (!q || !items || !items.length) return false;
    var exact = items.filter(function (c) {
      return String(c.name).toLowerCase() === q;
    });
    if (exact.length === 1) {
      pickItem(container, exact[0].id, exact[0].name);
      return true;
    }
    if (items.length === 1 && String(items[0].name).toLowerCase().indexOf(q) !== -1) {
      pickItem(container, items[0].id, items[0].name);
      return true;
    }
    return false;
  }

  function renderResults(container, items) {
    var state = registry.get(container);
    if (!state) return;
    state.lastResults = items || [];
    state.results.innerHTML = "";
    if (!items || !items.length) {
      state.results.classList.remove("is-open");
      state.results.style.display = "none";
      return;
    }
    state.results.style.display = "block";
    state.results.classList.add("is-open");
    items.forEach(function (ch) {
      var item = document.createElement("div");
      item.className = "rpg-char-search-item";
      item.textContent = ch.name;
      item.dataset.id = ch.id;
      item.addEventListener("mousedown", function (e) {
        e.preventDefault();
        pickItem(container, ch.id, ch.name);
      });
      state.results.appendChild(item);
    });
  }

  function init(container, options) {
    options = options || {};
    var input = container.querySelector(".char-search-input");
    var results = container.querySelector(".char-search-results");
    var hidden = container.querySelector(".char-search-value");
    if (!input || !results || !hidden) return null;

    var state = {
      input: input,
      results: results,
      hidden: hidden,
      fetchUrl: options.fetchUrl || "../ajax/cards_search_characters.php",
      onSelect: options.onSelect || null,
      debounceMs: options.debounceMs || 250,
      fetchTimeout: null,
      selectedName: hidden.value ? input.value.trim() : "",
      lastResults: []
    };
    registry.set(container, state);

    input.addEventListener("input", function () {
      clearTimeout(state.fetchTimeout);
      var q = input.value.trim();
      if (!q) {
        results.classList.remove("is-open");
        results.style.display = "none";
        hidden.value = "";
        state.selectedName = "";
        state.lastResults = [];
        return;
      }
      if (state.selectedName && q === state.selectedName && hidden.value) {
        return;
      }
      hidden.value = "";
      state.selectedName = "";

      state.fetchTimeout = setTimeout(function () {
        fetchCharacters(state, q).then(function (items) {
          renderResults(container, items);
          tryAutoPick(container, items, q);
        });
      }, state.debounceMs);
    });

    input.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") return;
      e.preventDefault();
      if (hidden.value) return;
      if (tryAutoPick(container, state.lastResults, input.value)) return;
      fetchCharacters(state, input.value.trim()).then(function (items) {
        tryAutoPick(container, items, input.value);
      });
    });

    input.addEventListener("blur", function () {
      setTimeout(function () {
        results.classList.remove("is-open");
        results.style.display = "none";
      }, 200);
      if (hidden.value) return;
      var q = input.value.trim();
      if (!q) return;
      if (state.lastResults.length && tryAutoPick(container, state.lastResults, q)) return;
      fetchCharacters(state, q).then(function (items) {
        tryAutoPick(container, items, q);
      });
    });

    input.addEventListener("focus", function () {
      if (results.children.length > 0) {
        results.style.display = "block";
        results.classList.add("is-open");
      }
    });

    return container;
  }

  function initAll(selector, options) {
    document.querySelectorAll(selector || ".character-search").forEach(function (node) {
      init(node, options);
    });
  }

  function getValue(container) {
    var hidden = container.querySelector(".char-search-value");
    return hidden ? String(hidden.value || "").trim() : "";
  }

  function resolve(container) {
    var val = getValue(container);
    if (val) return Promise.resolve(val);
    var state = registry.get(container);
    if (!state) return Promise.resolve("");
    var q = state.input.value.trim();
    if (!q) return Promise.resolve("");
    if (state.lastResults.length && tryAutoPick(container, state.lastResults, q)) {
      return Promise.resolve(getValue(container));
    }
    return fetchCharacters(state, q).then(function (items) {
      tryAutoPick(container, items, q);
      return getValue(container);
    });
  }

  global.GameCharSearch = {
    init: init,
    initAll: initAll,
    getValue: getValue,
    resolve: resolve
  };
})(window);
