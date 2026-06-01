import re, json
from pathlib import Path

base = Path(r"c:/Users/InformaTeam/Desktop/b2b Project/e-comerce/backend")
routes_file = base / "routes" / "api.php"
postman_file = base / "postman" / "B2B-Mediation-API.postman_collection.json"

def get_routes():
    if not routes_file.exists(): return []
    text = routes_file.read_text(encoding="utf-8")
    lines = text.splitlines()
    prefix_stack = [""]
    routes = []
    for line in lines:
        s = line.strip()
        m = re.search(r"Route::prefix\(['\"]([^'\"]+)['\"]\)->group\(function", s)
        if m:
            cur = prefix_stack[-1]
            new = (cur + "/" + m.group(1)).replace("//", "/")
            prefix_stack.append(new)
            continue
        if s.startswith("});"):
            if len(prefix_stack) > 1: prefix_stack.pop()
            continue
        m2 = re.search(r"Route::(get|post|put|patch|delete)\s*\(\s*['\"]([^'\"]+)['\"]", s, re.I)
        if m2:
            method = m2.group(1).upper()
            path = m2.group(2)
            prefix = prefix_stack[-1]
            full = (prefix + "/" + path).replace("//", "/")
            routes.append((method, full))
        m3 = re.search(r"Route::apiResource\(\s*['\"]([^'\"]+)['\"]", s)
        if m3:
            resource = m3.group(1)
            prefix = prefix_stack[-1]
            basep = (prefix + "/" + resource).replace("//", "/")
            routes.extend([
                ("GET", basep), ("POST", basep),
                ("GET", basep + "/{id}"), ("PATCH", basep + "/{id}"), ("DELETE", basep + "/{id}")
            ])
    return routes

def get_postman():
    if not postman_file.exists(): return []
    with open(postman_file, "r", encoding="utf-8") as f:
        data = json.load(f)
    endpoints = []
    def walk(items):
        for it in items:
            if "request" in it:
                req = it["request"]
                method = req.get("method", "GET").upper()
                url = req.get("url")
                path = ""
                if isinstance(url, dict):
                    if "path" in url and isinstance(url["path"], list):
                        path = "/" + "/".join([str(p) for p in url["path"] if p])
                    elif "raw" in url:
                        path = url["raw"]
                elif isinstance(url, str):
                    path = url
                endpoints.append((method, path))
            if "item" in it:
                walk(it["item"])
    walk(data.get("item", []))
    return endpoints

def normalize(path):
    path = re.sub(r"https?://[^/]+", "", path)
    path = path.split("?")[0]
    path = re.sub(r"\{\{.*?\}\}", "{id}", path)
    path = re.sub(r"\{.*?\}", "{id}", path)
    path = re.sub(r"/\d+", "/{id}", path)
    path = re.sub(r"/+", "/", path).strip("/")
    return path

laravel_routes = get_routes()
postman_endpoints = get_postman()
missing = []
norm_pm = set((m, normalize(p)) for m, p in postman_endpoints)
for m, p in sorted(set(laravel_routes)):
    if (m, normalize(p)) not in norm_pm:
        missing.append((m, p))

print(json.dumps({"laravel_count": len(set(laravel_routes)), "postman_count": len(postman_endpoints), "missing": missing}, indent=2))
