<?php
/*
Helpers for php templates.
*/

$cached_files = [
/*
    "<fullpath>": {
        contents: "contents",
        snippets: {
            "snippet_id": "contents"
        }
    }
*/
];

function _key($path, $sid) {
    return $path . ":" . $sid;
}

/* idempotent. */
function _cache_file($path) {
    global $cached_files;

    if (array_key_exists($path, $cached_files)) return;

    $contents = file($path, FILE_IGNORE_NEW_LINES);
    $cache_entry = [
        "contents" => "",
        "snippets" => []
    ];
    $current_snippet = "";
    $current_snippet_id = null;
    $filtered_contents = [];
    foreach($contents as $line) {
        $matches = [];
        if (preg_match("/SNIPPET_BEGIN:(.*)/", $line, $matches)) {
            $current_snippet_id = $matches[1];
            continue;
        }
        if (preg_match("/SNIPPET_END/", $line, $matches)) {
            $cache_entry["snippets"][$current_snippet_id] = $current_snippet;
            $current_snippet_id = null;
            $current_snippet = "";
            continue;
        }
        if ($current_snippet_id) {
            if ($current_snippet != "") $current_snippet .= "\n";
            $current_snippet .= $line;
        }
        array_push($filtered_contents, $line);
    }
    $cache_entry["contents"] = implode("\n", $filtered_contents);
    $cached_files[$path] = $cache_entry;
}

/*
 * call snippet(filename) to retrieve the entire file (stripped of snippet comments).
 * call snippet(filename, sid) to retrieve a slice of the document by snippet id.
 */
function snippet($file, $sid="") {
    global $cached_files, $snippets;

    $path = realpath($file);
    if ($path === NULL) {
        printf("file %s not found\n", $file);
        exit(1);
    }

    _cache_file ($path);

    if ($sid == "") return $cached_files[$path]["contents"];

    if (!array_key_exists($sid, $cached_files[$path]["snippets"])) {
        printf("file %s cached, but no entry for snippet %s found\n", $path, $sid);
        exit(1);
    }

    return $cached_files[$path]["snippets"][$sid];
}

function code($syntax, $file, $sid="", $opts=NULL) {
    /* TODO: add an option to "unindent" a snippet to remove prefixed whitespace */
    return sprintf("<pre><code class='%s'>%s</code></pre>", $syntax, htmlentities(snippet($file, $sid)));
}

function meta($field) {
    global $meta;
    if (!array_key_exists($field, $meta)) {
        printf("could not find field %s in metadata\n", $field);
        exit(1);
    }
    return $meta[$field];
}

$current_levels=[0];
/* use relative_level to nest relative to the parent heading.
 * use absolute_level to specify an absolute nesting level. */
function heading($text, $relative_level=0, $absolute_level=-1) {
    global $current_levels;

    $id = preg_replace("/\\s/", "_", $text);
    if ($absolute_level != -1) {
        $relative_level = $absolute_level - count($current_levels);
    }
    if ($relative_level > 1) exit("cannot jump down more than two levels.");
    if ($relative_level == 1) {
        array_push($current_levels, 0);
    } else if ($relative_level < 0) {
        $current_levels = array_slice($current_levels, 0, count($current_levels) + $relative_level);
    }

    $current_levels[count($current_levels) - 1]++;
    $section = "§" . implode($current_levels, ".");
    return sprintf("<h3 id='%s'><a href='#%s'>%s %s</a></h3>", $id, $id, $section, $text);
}