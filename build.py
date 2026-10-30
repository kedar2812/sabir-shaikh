"""Stamp the shared head, header and footer into every page.

Run `python build.py` after editing anything in _partials/. Each page marks
where a partial goes with <!-- @head -->...<!-- /head --> (same for header and
footer); the header marker can name the current section: <!-- @header key=blogs -->.
"""
import pathlib, re

ROOT = pathlib.Path(__file__).parent
parts = {n: (ROOT / "_partials" / f"{n}.html").read_text(encoding="utf-8").strip() for n in ("head", "header", "footer")}

for page in ROOT.rglob("*.html"):
    if "_partials" in page.parts:
        continue
    depth = len(page.relative_to(ROOT).parts) - 1
    # 404.html is served at whatever URL was missing, so it needs root-absolute paths.
    prefix = "/" if page.name == "404.html" else "../" * depth
    src = page.read_text(encoding="utf-8")

    def fill(m):
        name, args = m.group(1), m.group(2) or ""
        html = parts[name].replace("{{root}}", prefix)
        key = re.search(r"key=(\w+)", args)
        if key:
            html = html.replace(f'data-key="{key.group(1)}"', f'data-key="{key.group(1)}" aria-current="page"')
        return f"<!-- @{name}{args} -->\n{html}\n<!-- /{name} -->"

    out = re.sub(r"<!-- @(head|header|footer)([^>]*?) -->.*?<!-- /\1 -->", fill, src, flags=re.S)
    if out != src:
        page.write_text(out, encoding="utf-8")
        print("built", page.relative_to(ROOT))
