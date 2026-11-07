<?php
// Photo uploads. Every upload is decoded and re-encoded as WebP, so whatever was
// uploaded (a phone JPEG, a PNG screenshot) leaves only a clean image file behind,
// plus smaller copies for phones.

const MEDIA_MAX = 2400;
const MEDIA_WIDTHS = [640, 1280];

function media_store($tmp, $originalName)
{
    @ini_set('memory_limit', '256M');
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        return [null, 'That file isn\'t a photo the site can use. Upload a JPG, PNG or WebP.'];
    }
    if ($info[0] * $info[1] > 50000000) return [null, 'That photo is too large to process. Export it at under 7,000 pixels on the long side and try again.'];

    $img = @imagecreatefromstring(file_get_contents($tmp));
    if (!$img) return [null, 'That photo couldn\'t be read. Try exporting it again as a JPG.'];
    if ($info[2] === IMAGETYPE_JPEG) $img = media_orient($img, $tmp);
    if ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_WEBP) {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, MEDIA_MAX / max($w, $h));
    if ($scale < 1) {
        $img = media_resize($img, (int) round($w * $scale), (int) round($h * $scale));
        $w = imagesx($img);
        $h = imagesy($img);
    }

    $dir = '/uploads/' . date('Y/m');
    if (!is_dir(ROOT . $dir)) mkdir(ROOT . $dir, 0755, true);
    $base = slugify(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'photo';
    $base = substr($base, 0, 50) . '-' . bin2hex(random_bytes(3));
    $path = $dir . '/' . $base . '.webp';
    imagewebp($img, ROOT . $path, 82);

    $sizes = [];
    foreach (MEDIA_WIDTHS as $target) {
        if ($w <= $target * 1.15) continue;
        $copy = media_resize($img, $target, (int) round($h * $target / $w));
        $p = $dir . '/' . $base . '-' . $target . '.webp';
        imagewebp($copy, ROOT . $p, 80);
        imagedestroy($copy);
        $sizes[] = ['w' => $target, 'src' => $p];
    }
    imagedestroy($img);

    $name = trim(preg_replace('~[-_]+~', ' ', pathinfo($originalName, PATHINFO_FILENAME)));
    q('INSERT INTO media (path, name, alt, width, height, bytes, sizes, created_at) VALUES (?,?,?,?,?,?,?,?)', [
        $path, mb_substr($name, 0, 80), '', $w, $h, filesize(ROOT . $path), jenc($sizes), now(),
    ]);
    return [media_row((int) db()->lastInsertId()), null];
}

function media_resize($img, $w, $h)
{
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
    return $out;
}

// Phones save portrait photos sideways with a rotation flag; apply it.
function media_orient($img, $file)
{
    if (!function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($file);
    $o = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
    if ($o === 3) return imagerotate($img, 180, 0);
    if ($o === 6) return imagerotate($img, -90, 0);
    if ($o === 8) return imagerotate($img, 90, 0);
    return $img;
}

function media_row($id)
{
    $m = q_one('SELECT * FROM media WHERE id = ?', [$id]);
    if (!$m) return null;
    $m['id'] = (int) $m['id'];
    $m['width'] = (int) $m['width'];
    $m['height'] = (int) $m['height'];
    $m['bytes'] = (int) $m['bytes'];
    $m['sizes'] = json_decode($m['sizes'], true) ?: [];
    return $m;
}

// Where a photo is used, so deleting one never leaves a hole on the site unnoticed.
function media_usage($path)
{
    $uses = [];
    $plain = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $path) . '%';
    foreach (q_all("SELECT key, value FROM content") as $row) {
        if (strpos($row['value'], $path) !== false) {
            $s = content_section($row['key']);
            $uses[] = ($s ? $s['label'] : $row['key']);
        }
    }
    foreach (q_all("SELECT title FROM posts WHERE cover LIKE ? ESCAPE '\\' OR body LIKE ? ESCAPE '\\'", [$plain, $plain]) as $p) $uses[] = 'Essay: ' . $p['title'];
    foreach (q_all("SELECT title FROM episodes WHERE image LIKE ? ESCAPE '\\'", [$plain]) as $e) $uses[] = 'Episode: ' . $e['title'];
    // Defaults that were never edited still point at the launch photos.
    foreach (content_schema() as $s) {
        $saved = q_val('SELECT 1 FROM content WHERE key = ?', [$s['key']]);
        if ($saved) continue;
        if (strpos(jenc(content_defaults($s)), $path) !== false) $uses[] = $s['label'];
    }
    return array_values(array_unique($uses));
}
