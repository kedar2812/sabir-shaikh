<?php
// Cleans HTML from the dashboard's writing editor down to a small set of tags,
// so a pasted Word document or web page can't bring scripts or stray styling
// onto the site.

function clean_html($html)
{
    $html = trim((string) $html);
    if ($html === '') return '';

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body><div id="__root">' . $html . '</div></body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('__root');
    if (!$root) return '';
    clean_children($doc, $root);
    wrap_loose_inline($doc, $root);

    $out = '';
    foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
    // Drop paragraphs that hold nothing but whitespace or line breaks.
    $out = preg_replace('~<p>(\s|&nbsp;|<br>)*</p>~u', '', $out);
    return trim($out);
}

function clean_rules()
{
    return [
        'allowed' => [
            'p' => [], 'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'strong' => [], 'em' => [], 'u' => [],
            'a' => ['href', 'title', 'id'], 'ul' => [], 'ol' => [], 'li' => ['id'], 'blockquote' => [],
            'figure' => [], 'figcaption' => [], 'img' => ['src', 'alt', 'width', 'height'],
            'br' => [], 'hr' => [], 'sup' => ['id'], 'sub' => [], 'cite' => [], 'code' => [],
        ],
        'rename' => ['b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'div' => 'p'],
        'drop' => ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'title', 'head', 'canvas', 'video', 'audio'],
    ];
}

function clean_children(DOMDocument $doc, DOMNode $parent)
{
    $rules = clean_rules();
    $nodes = [];
    foreach ($parent->childNodes as $n) $nodes[] = $n;
    foreach ($nodes as $node) {
        if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
            $parent->removeChild($node);
            continue;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) continue;
        $tag = strtolower($node->nodeName);
        if (in_array($tag, $rules['drop'], true)) {
            $parent->removeChild($node);
            continue;
        }
        if (isset($rules['rename'][$tag])) {
            $node = rename_element($doc, $node, $rules['rename'][$tag]);
            $tag = $rules['rename'][$tag];
        }
        if (!isset($rules['allowed'][$tag])) {
            // Unknown wrapper (span, font, section...): keep what's inside it.
            clean_children($doc, $node);
            while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
            $parent->removeChild($node);
            continue;
        }
        $keep = $rules['allowed'][$tag];
        $attrs = [];
        foreach ($node->attributes as $a) $attrs[] = $a->nodeName;
        foreach ($attrs as $name) {
            $value = $node->getAttribute($name);
            if (!in_array(strtolower($name), $keep, true) || !clean_attr($tag, strtolower($name), $value)) $node->removeAttribute($name);
        }
        if ($tag === 'a') {
            if (!$node->hasAttribute('href')) {
                clean_children($doc, $node);
                while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                $parent->removeChild($node);
                continue;
            }
            if (is_external($node->getAttribute('href'))) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener');
            }
        }
        if ($tag === 'img' && !$node->hasAttribute('src')) {
            $parent->removeChild($node);
            continue;
        }
        clean_children($doc, $node);
    }
}

function clean_attr($tag, $name, $value)
{
    $value = trim($value);
    if ($name === 'id') return (bool) preg_match('~^[A-Za-z][A-Za-z0-9_-]{0,40}$~', $value);
    if ($name === 'href') return (bool) preg_match('~^(https?://|mailto:|tel:|#|/(?!/))~i', $value);
    if ($name === 'src') return (bool) preg_match('~^(/uploads/|/assets/|https://)~i', $value);
    if ($name === 'width' || $name === 'height') return ctype_digit($value);
    return true;
}

function rename_element(DOMDocument $doc, DOMElement $node, $tag)
{
    $new = $doc->createElement($tag);
    while ($node->firstChild) $new->appendChild($node->firstChild);
    $node->parentNode->replaceChild($new, $node);
    return $new;
}

// Text typed straight into the editor without a paragraph gets one.
function wrap_loose_inline(DOMDocument $doc, DOMNode $root)
{
    $blocks = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'blockquote', 'figure', 'hr'];
    $nodes = [];
    foreach ($root->childNodes as $n) $nodes[] = $n;
    $current = null;
    foreach ($nodes as $node) {
        $isBlock = $node->nodeType === XML_ELEMENT_NODE && in_array(strtolower($node->nodeName), $blocks, true);
        if ($isBlock) {
            $current = null;
            continue;
        }
        if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '' && !$current) {
            $root->removeChild($node);
            continue;
        }
        if (!$current) {
            $current = $doc->createElement('p');
            $root->insertBefore($current, $node);
        }
        $current->appendChild($node);
    }
}
