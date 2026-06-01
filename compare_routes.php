$routesFile = $argv[1];
$postmanFile = $argv[2];
$routes = file_get_contents($routesFile);
$lines = preg_split("/\r?\n/", $routes);
$prefixStack = [""];
$routesArr = [];
foreach ($lines as $line) {
    $s = trim($line);
    if (preg_match("/Route::prefix\([''']([^''']+)[''']\)->group\(function\(\) \{/", $s, $m)) {
        $cur = end($prefixStack);
        $new = rtrim($cur, "/") . "/" . trim($m[1], "/");
        $prefixStack[] = preg_replace("#/+#", "/", $new);
        continue;
    }
    if ($s == "});") {
        if (count($prefixStack) > 1) array_pop($prefixStack);
        continue;
    }
    if (preg_match("/Route::(get|post|put|patch|delete)\s*\(\s*[''' ]([^''' ]+)[''' ]/", $s, $m2)) {
        $method = strtoupper($m2[1]);
        $path = $m2[2];
        $prefix = end($prefixStack);
        $full = preg_replace("#/+#", "/", $prefix . "/" . $path);
        if (substr($full, 0, 1) != "/") $full = "/" . $full;
        $routesArr[] = [$method, $full];
        continue;
    }
    if (preg_match("/Route::apiResource\(\s*[''' ]([^''' ]+)[''' ]/", $s, $m3)) {
        $resource = $m3[1];
        $prefix = end($prefixStack);
        $basepath = preg_replace("#/+#", "/", $prefix . "/" . $resource);
        if (substr($basepath, 0, 1) != "/") $basepath = "/" . $basepath;
        $routesArr[] = ["GET", $basepath];
        $routesArr[] = ["POST", $basepath];
        $routesArr[] = ["GET", $basepath . "/{id}"];
        $routesArr[] = ["PATCH", $basepath . "/{id}"];
        $routesArr[] = ["DELETE", $basepath . "/{id}"];
        continue;
    }
}
$normRoutes = [];
foreach ($routesArr as $r) {
    list($m, $p) = $r;
    $p = preg_replace("#/+#", "/", $p);
    $normRoutes[$m . " " . $p] = 1;
}
$pm = json_decode(file_get_contents($postmanFile), true);
$pmSet = [];
function walk($items, &$pmSet) {
    if (!is_array($items)) return;
    foreach ($items as $it) {
        if (isset($it["request"])) {
            $req = $it["request"];
            $method = strtoupper($req["method"] ?? "GET");
            $url = $req["url"];
            $path = "";
            if (isset($url["path"]) && is_array($url["path"])) {
                $path = "/" . implode("/", $url["path"]);
            } elseif (isset($url["raw"])) {
                $raw = $url["raw"];
                $path = parse_url($raw, PHP_URL_PATH);
                if (!$path) {
                    if (strpos($raw, "{{") !== false) {
                        $p = preg_replace("/^https?:\/\/[^\/]+/", "", $raw);
                        $p = explode("?", $p)[0];
                        $path = $p;
                    } else {
                        $path = $raw;
                    }
                }
            }
            $path = preg_replace("#/+#", "/", "/".$path);
            $pmSet[$method . " " . $path] = 1;
        }
        if (isset($it["item"])) walk($it["item"], $pmSet);
    }
}
walk($pm["item"], $pmSet);
$missing = [];
foreach (array_keys($normRoutes) as $k) {
    $found = false;
    list($rMethod, $rPath) = explode(" ", $k, 2);
    $rNorm = rtrim(preg_replace("#\{[^/]+\}#", "{id}", $rPath), "/");
    foreach (array_keys($pmSet) as $pk) {
        list($pmMethod, $pmPath) = explode(" ", $pk, 2);
        if ($pmMethod !== $rMethod) continue;
        $pmNorm = preg_replace("#/\d+#", "/{id}", $pmPath);
        $pmNorm = rtrim(preg_replace("#\{[^/]+\}#", "{id}", $pmNorm), "/");
        if ($pmNorm === $rNorm) {
            $found = true;
            break;
        }
    }
    if (!$found) $missing[] = $k;
}
echo json_encode(["routes_count" => count($normRoutes), "postman_count" => count($pmSet), "missing" => $missing]);
