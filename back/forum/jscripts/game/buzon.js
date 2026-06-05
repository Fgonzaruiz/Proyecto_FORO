/**
 * Buzón — mensajes directos por personaje
 * Config: window.BUZON_CONFIG
 */
(function () {
  "use strict";

  var cfg = window.BUZON_CONFIG || {};
  if (!cfg.activePjId) return;

  var ajaxBase = cfg.ajaxBase || "";
  var currentFolder = cfg.initialTab === "sent" ? "sent" : "inbox";
  var inboxPage = 1;
  var sentPage = 1;
  var lastListFolder = "inbox";

  function dmFetch(path, options) {
    return fetch(ajaxBase + path, options || { credentials: "same-origin" }).then(function (r) {
      return r.json();
    });
  }

  function dmPost(path, payload) {
    var url = ajaxBase + path;
    var body = payload || {};
    if (window.gamePostJson) {
      return window.gamePostJson(url, body);
    }
    if (window.GAME_CSRF) {
      body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Mybb-Post-Key": window.GAME_CSRF || ""
      },
      credentials: "same-origin",
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json();
    });
  }

  function escapeHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatDate(iso) {
    if (!iso) return "";
    var d = new Date(iso.replace(" ", "T"));
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleDateString("es-ES", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
  }

  function showPanel(name) {
    document.querySelectorAll(".buzon-panel").forEach(function (p) {
      p.classList.add("is-hidden");
    });
    var panel = document.getElementById("buzon-panel-" + name);
    if (panel) panel.classList.remove("is-hidden");

    document.querySelectorAll(".buzon-nav-btn").forEach(function (btn) {
      btn.classList.toggle("is-active", btn.getAttribute("data-tab") === name);
    });
  }

  function renderMessageRow(item, folder) {
    var unreadClass = folder === "inbox" && !item.is_read ? " buzon-row--unread" : "";
    var peer = folder === "sent" ? "Para: " + escapeHtml(item.to_name) : "De: " + escapeHtml(item.from_name);
    return (
      '<button type="button" class="buzon-row' + unreadClass + '" data-id="' + item.id + '">' +
      '  <div class="buzon-row-top">' +
      '    <span class="buzon-row-peer">' + peer + "</span>" +
      '    <span class="buzon-row-date">' + formatDate(item.created_at) + "</span>" +
      "  </div>" +
      '  <div class="buzon-row-subject">' + escapeHtml(item.subject) + "</div>" +
      '  <div class="buzon-row-preview">' + escapeHtml(item.body_preview || "") + "</div>" +
      "</button>"
    );
  }

  function renderPagination(containerId, data, onPage) {
    var el = document.getElementById(containerId);
    if (!el || data.total_pages <= 1) {
      if (el) el.innerHTML = "";
      return;
    }
    var html = "";
    for (var p = 1; p <= data.total_pages; p++) {
      html += '<button type="button" class="buzon-page-btn' + (p === data.page ? " is-active" : "") + '" data-page="' + p + '">' + p + "</button>";
    }
    el.innerHTML = html;
    el.querySelectorAll(".buzon-page-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        onPage(parseInt(btn.getAttribute("data-page"), 10));
      });
    });
  }

  function loadList(folder, page) {
    var listId = folder === "sent" ? "buzon-sent-list" : "buzon-inbox-list";
    var pagId = folder === "sent" ? "buzon-sent-pagination" : "buzon-inbox-pagination";
    var listEl = document.getElementById(listId);
    if (!listEl) return;

    listEl.innerHTML = '<div class="buzon-loading"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

    dmFetch("/dm_list.php?folder=" + folder + "&page=" + page + "&per_page=20")
      .then(function (res) {
        if (!res.ok || !res.data) {
          listEl.innerHTML = '<div class="buzon-empty"><i class="fas fa-exclamation-triangle"></i><p>Error al cargar mensajes.</p></div>';
          return;
        }
        var data = res.data;
        if (!data.items || data.items.length === 0) {
          listEl.innerHTML =
            '<div class="buzon-empty"><i class="fas fa-inbox"></i><p>No hay mensajes en esta carpeta.</p></div>';
        } else {
          listEl.innerHTML = data.items.map(function (item) {
            return renderMessageRow(item, folder);
          }).join("");
          listEl.querySelectorAll(".buzon-row").forEach(function (row) {
            row.addEventListener("click", function () {
              openMessage(parseInt(row.getAttribute("data-id"), 10));
            });
          });
        }
        renderPagination(pagId, data, function (p) {
          if (folder === "sent") {
            sentPage = p;
            loadList("sent", p);
          } else {
            inboxPage = p;
            loadList("inbox", p);
          }
        });
        updateUnreadBadge();
      })
      .catch(function () {
        listEl.innerHTML = '<div class="buzon-empty"><i class="fas fa-wifi"></i><p>Error de conexión.</p></div>';
      });
  }

  function openMessage(id) {
    lastListFolder = currentFolder;
    dmFetch("/dm_read.php?id=" + id)
      .then(function (res) {
        if (!res.ok || !res.data) {
          alert(res.error ? res.error.message : "No se pudo leer el mensaje.");
          return;
        }
        var m = res.data;
        var content = document.getElementById("buzon-read-content");
        if (!content) return;
        content.innerHTML =
          '<header class="buzon-read-header">' +
          '  <h2 class="buzon-read-subject">' + escapeHtml(m.subject) + "</h2>" +
          '  <div class="buzon-read-meta">' +
          '    <span><strong>De:</strong> ' + escapeHtml(m.from_name) + "</span>" +
          '    <span><strong>Para:</strong> ' + escapeHtml(m.to_name) + "</span>" +
          '    <span><strong>Fecha:</strong> ' + formatDate(m.created_at) + "</span>" +
          "  </div>" +
          "</header>" +
          '<div class="buzon-read-body">' + escapeHtml(m.body).replace(/\n/g, "<br>") + "</div>" +
          '<div class="buzon-read-actions">' +
          '  <button type="button" class="rpg-btn--secondary buzon-delete-btn" data-id="' + m.id + '"><i class="fas fa-trash"></i> Eliminar</button>' +
          (m.is_inbox
            ? '  <button type="button" class="rpg-btn--primary buzon-reply-btn" data-id="' + m.from_character_id + '" data-name="' + escapeHtml(m.from_name) + '"><i class="fas fa-reply"></i> Responder</button>'
            : "") +
          "</div>";
        var delBtn = content.querySelector(".buzon-delete-btn");
        if (delBtn) {
          delBtn.addEventListener("click", function () {
            deleteMessage(parseInt(delBtn.getAttribute("data-id"), 10));
          });
        }
        var replyBtn = content.querySelector(".buzon-reply-btn");
        if (replyBtn) {
          replyBtn.addEventListener("click", function () {
            selectRecipient(parseInt(replyBtn.getAttribute("data-id"), 10), replyBtn.getAttribute("data-name"));
            showPanel("compose");
            document.querySelector('.buzon-nav-btn[data-tab="compose"]').classList.add("is-active");
          });
        }
        showPanel("read");
        updateUnreadBadge();
        updateNavBadge();
      })
      .catch(function () {
        alert("Error de conexión.");
      });
  }

  function deleteMessage(id) {
    if (!confirm("¿Eliminar este mensaje?")) return;
    dmPost("/dm_delete.php", { id: id }).then(function (res) {
      if (res.ok) {
        showPanel(lastListFolder);
        loadList("inbox", inboxPage);
        loadList("sent", sentPage);
        updateUnreadBadge();
        updateNavBadge();
      } else {
        alert(res.error ? res.error.message : "No se pudo eliminar.");
      }
    });
  }

  function updateUnreadBadge() {
    var badge = document.getElementById("buzon-unread-badge");
    if (!badge) return;
    dmFetch("/dm_count.php").then(function (res) {
      if (res.ok && res.data) {
        var n = res.data.unread || 0;
        badge.textContent = n;
        badge.classList.toggle("is-hidden", n <= 0);
      }
    });
  }

  function updateNavBadge() {
    if (!window.fetch) return;
    dmFetch("/dm_count.php").then(function (res) {
      if (!res.ok || !res.data) return;
      var n = res.data.unread || 0;
      var navBadge = document.getElementById("nav-dm-badge");
      if (navBadge) {
        navBadge.textContent = n > 99 ? "99+" : n;
        navBadge.classList.toggle("is-hidden", n <= 0);
      }
    });
  }

  function selectRecipient(id, name) {
    var hidden = document.getElementById("buzon-to-id");
    var selected = document.getElementById("buzon-to-selected");
    var search = document.getElementById("buzon-to-search");
    var results = document.getElementById("buzon-to-results");
    if (hidden) hidden.value = id;
    if (selected) {
      selected.innerHTML = '<span class="buzon-chip"><i class="fas fa-user"></i> ' + escapeHtml(name) + '</span>';
      selected.classList.remove("is-hidden");
    }
    if (search) search.value = "";
    if (results) {
      results.classList.add("is-hidden");
      results.innerHTML = "";
    }
  }

  function initCompose() {
    var form = document.getElementById("buzon-compose-form");
    var search = document.getElementById("buzon-to-search");
    var results = document.getElementById("buzon-to-results");
    var msgEl = document.getElementById("buzon-compose-msg");
    var searchTimer = null;

    if (search && results) {
      search.addEventListener("input", function () {
        clearTimeout(searchTimer);
        var q = search.value.trim();
        if (q.length < 1) {
          results.classList.add("is-hidden");
          return;
        }
        searchTimer = setTimeout(function () {
          dmFetch("/dm_search_characters.php?q=" + encodeURIComponent(q))
            .then(function (res) {
              if (!res.ok || !res.data || !res.data.characters) return;
              if (res.data.characters.length === 0) {
                results.innerHTML = '<div class="buzon-search-empty">Sin resultados</div>';
              } else {
                results.innerHTML = res.data.characters
                  .map(function (c) {
                    return '<button type="button" class="buzon-search-item" data-id="' + c.id + '" data-name="' + escapeHtml(c.name) + '">' + escapeHtml(c.name) + "</button>";
                  })
                  .join("");
                results.querySelectorAll(".buzon-search-item").forEach(function (btn) {
                  btn.addEventListener("click", function () {
                    selectRecipient(parseInt(btn.getAttribute("data-id"), 10), btn.getAttribute("data-name"));
                  });
                });
              }
              results.classList.remove("is-hidden");
            })
            .catch(function () {});
        }, 250);
      });
    }

    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var toId = parseInt((document.getElementById("buzon-to-id") || {}).value || "0", 10);
        var subject = (document.getElementById("buzon-subject") || {}).value || "";
        var body = (document.getElementById("buzon-body") || {}).value || "";
        if (!toId) {
          if (msgEl) {
            msgEl.textContent = "Selecciona un personaje destinatario.";
            msgEl.classList.remove("is-hidden");
          }
          return;
        }
        dmPost("/dm_send.php", {
          to_character_id: toId,
          subject: subject,
          body: body
        }).then(function (res) {
          if (res.ok) {
            form.reset();
            var selected = document.getElementById("buzon-to-selected");
            if (selected) {
              selected.classList.add("is-hidden");
              selected.innerHTML = "";
            }
            if (msgEl) {
              msgEl.innerHTML = '<span class="rpg-text-success"><i class="fas fa-check-circle"></i> Mensaje enviado.</span>';
              msgEl.classList.remove("is-hidden");
            }
            loadList("sent", 1);
            showPanel("sent");
          } else if (msgEl) {
            msgEl.textContent = res.error ? res.error.message : "Error al enviar.";
            msgEl.classList.remove("is-hidden");
          }
        });
      });
    }

    if (cfg.toCharacterId > 0 && cfg.toCharacterName) {
      selectRecipient(cfg.toCharacterId, cfg.toCharacterName);
    }
  }

  function initNav() {
    document.querySelectorAll(".buzon-nav-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var tab = btn.getAttribute("data-tab");
        currentFolder = tab === "sent" ? "sent" : "inbox";
        showPanel(tab);
        if (tab === "inbox") loadList("inbox", inboxPage);
        if (tab === "sent") loadList("sent", sentPage);
      });
    });

    var openCompose = document.getElementById("buzon-open-compose");
    if (openCompose) {
      openCompose.addEventListener("click", function () {
        showPanel("compose");
        document.querySelectorAll(".buzon-nav-btn").forEach(function (b) {
          b.classList.toggle("is-active", b.getAttribute("data-tab") === "compose");
        });
      });
    }

    var backBtn = document.getElementById("buzon-back-list");
    if (backBtn) {
      backBtn.addEventListener("click", function () {
        showPanel(lastListFolder);
      });
    }
  }

  function init() {
    initNav();
    initCompose();
    loadList("inbox", inboxPage);
    loadList("sent", sentPage);
    updateUnreadBadge();

    if (cfg.initialTab === "compose") {
      showPanel("compose");
    } else if (cfg.initialTab === "sent") {
      showPanel("sent");
    }

    if (cfg.readId > 0) {
      openMessage(cfg.readId);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
