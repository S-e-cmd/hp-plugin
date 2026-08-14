<?php
$html = <<<'HTML'
<head>
<meta property="og:type" content="article" />
<meta property="og:url" content="https://uenobotanen.com/english/" />
<meta property="og:title" content="English" />
<meta property="og:description" content="Japanese old description" />
<meta property="og:site_name" content="Japanese site" />
<meta property="og:image" content="old.png" />
<meta property="fb:admins" content="123" />
<!-- All in One SEO 5.0.0.1 - aioseo.com -->
<meta property="og:locale" content="en_US" />
<meta property="og:title" content="Ueno Toshogu Peony Garden | Peonies &amp; Dahlias in Tokyo" />
<meta property="og:description" content="English description" />
<meta property="og:image" content="new.png" />
</head>
HTML;
$marker = '<!-- All in One SEO';
$pos = stripos($html, $marker);
if ($pos === false) exit(1);
$before = substr($html, 0, $pos);
$after = substr($html, $pos);
$pattern = '~<meta\\b(?=[^>]*\\bproperty\\s*=\\s*(["\\\'])og:(?:type|url|title|description|site_name|image(?::(?:secure_url|width|height))?)\\1)[^>]*>\\s*~i';
$before = preg_replace($pattern, '', $before);
if (!is_string($before)) exit(2);
$out = $before . $after;
if (strpos($out, 'content="English"') !== false) exit(3);
if (strpos($out, 'Japanese old description') !== false) exit(4);
if (strpos($out, 'old.png') !== false) exit(5);
if (strpos($out, 'fb:admins') === false) exit(6);
if (strpos($out, 'en_US') === false) exit(7);
if (strpos($out, 'Ueno Toshogu Peony Garden') === false) exit(8);
if (strpos($out, 'English description') === false) exit(9);
if (strpos($out, 'new.png') === false) exit(10);
echo "OG regression test passed\n";
