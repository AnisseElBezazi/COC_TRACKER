<?php
/**
 * Assemble assets/css/*.css en un seul fichier public/css/site.css : une requête au lieu de cinq,
 * polices et petites images embarquées (data:), commentaires et espaces retirés.
 *
 * Usage : php tools/build-css.php [--sans-clash]
 */
$racine = \dirname(__DIR__);
// --sans-clash : thème bleu nuit d'origine (app.css), sans la surcouche marron « Clash » (clash.css).
$ordre = \in_array('--sans-clash', $argv ?? [], true) ? ['app', 'motion', 'compact', 'meta', 'meta-bleu'] : ['app', 'clash', 'motion', 'compact', 'meta'];
$css = '';
foreach ($ordre as $nom) {
    $css .= file_get_contents("$racine/assets/css/$nom.css")."\n";
}

// Les polices sont déclarées dans app.css puis redéclarées dans motion.css (font-display:block) : on garde la dernière.
preg_match_all('/@font-face\{[^}]*\}/', $css, $faces);
$vus = [];
foreach (array_reverse($faces[0]) as $face) {
    preg_match("/font-family:'([^']+)'.*font-weight:(\d+)/", $face, $k);
    $cle = $k[1].$k[2];
    if (isset($vus[$cle])) {
        $css = str_replace($face, '', $css);
    }
    $vus[$cle] = true;
}

// Embarque les fichiers de moins de 40 Ko.
$css = preg_replace_callback('/url\(([\'"]?)(\/(?:fonts|img)\/[^\'")]+)\1\)/', static function (array $m) use ($racine) {
    $fichier = $racine.'/public'.$m[2];
    if (!is_file($fichier) || filesize($fichier) > 40_000) {
        return $m[0];
    }
    $type = ['woff2' => 'font/woff2', 'png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg'][pathinfo($fichier, \PATHINFO_EXTENSION)];

    return 'url(data:'.$type.';base64,'.base64_encode(file_get_contents($fichier)).')';
}, $css);

$css = preg_replace('#/\*.*?\*/#s', '', $css);
$css = preg_replace('/\s+/', ' ', $css);
$css = preg_replace('/\s*([{};,>])\s*/', '$1', $css);
$css = str_replace(';}', '}', $css);

file_put_contents("$racine/public/css/site.css", $css);
printf("public/css/site.css : %d Ko\n", (int) round(\strlen($css) / 1024));
