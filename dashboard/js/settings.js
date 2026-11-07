// Settings: the account, the password, and two choices that belong to this
// browser only (counting your own visits, light or dark appearance).

import { h, api, state, toast, pageHead, segmented, toggle, button, setVisitIgnore, visitIgnored } from "./lib.js";

function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
function set(k, v) { try { if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) { /* storage blocked */ } }

export function applyTheme() {
  const t = get("dash_theme");
  if (t === "light" || t === "dark") document.documentElement.dataset.theme = t;
  else delete document.documentElement.dataset.theme;
}

function field(label, input, err, help) {
  return h("div.field", h("label.field__label", { for: input.id }, label), help ? h("p.field__help", help) : null, input, err);
}

function showErrors(map, fields) {
  Object.values(map).forEach((el) => { el.textContent = ""; el.closest(".field").classList.remove("has-error"); });
  Object.entries(fields || {}).forEach(([k, msg]) => {
    if (!map[k]) return;
    map[k].textContent = msg;
    map[k].closest(".field").classList.add("has-error");
  });
}

export function settingsView(root) {
  root.classList.add("view--narrow");
  root.append(pageHead("Settings", "Your sign-in details, and how the dashboard behaves on this browser."));

  // ---------------------------------------------------------------- Account
  const name = h("input.input", { id: "st-name", type: "text", value: state.user.name || "", autocomplete: "name" });
  const username = h("input.input", { id: "st-user", type: "text", value: state.user.username || "", autocomplete: "username", autocapitalize: "none", spellcheck: "false" });
  const accErr = { name: h("p.field__err"), username: h("p.field__err") };
  const saveAcc = button("Save", { kind: "primary", onClick: async () => {
    saveAcc.classList.add("is-busy");
    try {
      const u = await api("PUT", "account", { name: name.value, username: username.value });
      state.user = Object.assign({}, state.user, u);
      window.dispatchEvent(new CustomEvent("userchange"));
      showErrors(accErr, {});
      toast("Account saved");
    } catch (e) {
      showErrors(accErr, e.fields);
      toast(e.message, "error");
    } finally { saveAcc.classList.remove("is-busy"); }
  } });

  // ---------------------------------------------------------------- Password
  const cur = h("input.input", { id: "st-cur", type: "password", autocomplete: "current-password" });
  const next = h("input.input", { id: "st-next", type: "password", autocomplete: "new-password" });
  const again = h("input.input", { id: "st-again", type: "password", autocomplete: "new-password" });
  const pwErr = { current: h("p.field__err"), next: h("p.field__err"), again: h("p.field__err") };
  const savePw = button("Change password", { kind: "primary", onClick: async () => {
    if (next.value !== again.value) { showErrors(pwErr, { again: "The two new passwords don't match." }); return; }
    savePw.classList.add("is-busy");
    try {
      await api("POST", "account/password", { current: cur.value, next: next.value });
      cur.value = next.value = again.value = "";
      showErrors(pwErr, {});
      toast("Password changed");
    } catch (e) {
      showErrors(pwErr, e.fields);
      toast(e.message, "error");
    } finally { savePw.classList.remove("is-busy"); }
  } });

  // ---------------------------------------------------------------- This browser
  const visits = toggle(visitIgnored(), (on) => {
    setVisitIgnore(on);
    set("ss_count_me", on ? null : "1");
    toast(on ? "Your visits from this browser won't be counted" : "Your visits from this browser will be counted");
  }, "Leave my visits out");
  const theme = segmented([
    { value: "system", label: "Match the device" },
    { value: "light", label: "Light" },
    { value: "dark", label: "Dark" },
  ], get("dash_theme") || "system", (v) => { set("dash_theme", v === "system" ? null : v); applyTheme(); }, "Appearance");

  root.append(h("div.settings",
    h("section.card",
      h("header.card__head", h("div", h("h2.card__title", "Account"), h("p.card__sub", "The dashboard greets you by this name."))),
      h("div.card__body",
        h("div.fields", field("Name", name, accErr.name), field("Username", username, accErr.username, "Used to sign in.")),
        h("div.settings__actions", saveAcc))),
    h("section.card",
      h("header.card__head", h("div", h("h2.card__title", "Password"), h("p.card__sub", "After five wrong tries, signing in pauses for 15 minutes."))),
      h("div.card__body",
        h("div.fields",
          field("Current password", cur, pwErr.current),
          field("New password", next, pwErr.next, "At least 10 characters. A short sentence you'll remember works well."),
          field("New password again", again, pwErr.again)),
        h("div.settings__actions", savePw))),
    h("section.card",
      h("header.card__head", h("div", h("h2.card__title", "This browser"), h("p.card__sub", "These choices are saved on this device only."))),
      h("div.card__body", { style: { display: "flex", flexDirection: "column", gap: "22px" } },
        h("div.settings__row", h("div", h("p.field__label", "Your own visits"), h("p", "When on, opening the site from this browser doesn't add to the visitor numbers.")), visits),
        h("div", h("p.field__label", { style: { marginBottom: "10px" } }, "Appearance"), theme)))));
}
