<?php ini_set('default_charset','UTF-8');header('Content-Type: text/html; charset=UTF-8'); ?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Site Search Results</title>
<meta name="referrer" content="same-origin">
<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
<script>!function(){var A=new Image;A.onload=A.onerror=function(){1==A.height&&(document.documentElement.className+=" webp")},A.src="data:image/webp;base64,UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAD8D+JaQAA3AA/ua1AAA"}();</script>
<link rel="stylesheet" href="css/site.92c872.css" type="text/css">
<!--[if lte IE 7]>
<link rel="stylesheet" href="css/site.92c872-lteIE7.css" type="text/css">
<![endif]-->
</head>
<body id="b1">
<?php
    $mb = extension_loaded('mbstring');

    function find($searchText, $searchFor) {
        global $mb;
        return $mb ? mb_stripos($searchText, $searchFor) : stripos($searchText, $searchFor);
    }

    function mb_split_str($str) {
        preg_match_all("/./u", $str, $arr);
        return $arr[0];
    }

    function mb_similar_text($str1, $str2, &$percent) {
        global $mb;
        $arr_1 = array_unique(mb_split_str($str1));
        $arr_2 = array_unique(mb_split_str($str2));
        $similarity = count($arr_2) - count(array_diff($arr_2, $arr_1));
        $percent = ($similarity * 200) / ($mb ? (mb_strlen($str1) + mb_strlen($str2)) : (strlen($str1) + strlen($str2)));
        return $similarity;
    }

    function simfind($searchText, $searchWord, &$score) {
        global $mb;
        $s = strtoupper($searchWord);
        foreach(preg_split("/[\\s]+/", $searchText) as $w) {
            mb_similar_text($s, strtoupper($w), $percent);
            if($percent >= 80) {
                $score = $percent / 100;
                return array($mb ? mb_stripos($searchText, $w) : stripos($searchText, $w), $mb ? mb_strlen($w) : strlen($w));
            }
        }
        return array(FALSE, FALSE);
    }

    function scorecmp($a, $b) {
        if ($a['score'] == $b['score']) {
            return 0;
        }
        return ($a['score'] < $b['score']) ? 1 : -1;
    }

    function textlencmp($a, $b) {
        if (strlen($a['text']) == strlen($b['text'])) {
            return 0;
        }
        return (strlen($a['text']) < strlen($b['text'])) ? 1 : -1;
    }

    function snipcmp($a, $b) {
        if ($a['score'] == $b['score']) {
            return textlencmp($a, $b);
        }
        return ($a['score'] < $b['score']) ? 1 : -1;
    }

    function ordercmp($a, $b) {
        if ($a['order'] == $b['order']) {
            return 0;
        }
        return ($a['order'] < $b['order']) ? 1 : -1;
    }

    function mfind($searchText, $searchFor, $words, $w, &$wordsfound) {
        if(empty($searchFor))
            return FALSE;

        $snippet = array('text' => $searchText, 'w' => $w);
        if(($pos = find($searchText, $searchFor)) !== FALSE) {
            $wordsfound = array_merge($wordsfound, $words);
            $snippet['score'] = 20;
            $snippet['pos'] = $pos;
            $snippet['matchlen'] = strlen($searchFor);
            return $snippet;
        }
        foreach($words as $searchWord) {
            if(($pos = find($searchText, $searchWord)) !== FALSE) {
                $snippet['score'] = isset($wordscores[$searchWord]) ? 0.5 : 10;
                $wordsfound[] = $searchWord;
                $snippet['pos'] = $pos;
                $snippet['matchlen'] = strlen($searchWord);
                return $snippet;
            }
        }
        foreach($words as $searchWord) {
            $score = 1;
            $match = simfind($searchText, $searchWord, $score);
            if($match[0] !== FALSE) {
                $wordsfound[] = $searchWord;
                $snippet['score'] = $score * 5;
                $snippet['pos'] = $match[0];
                $snippet['matchlen'] = $match[1];
                return $snippet;
            }
        }
        return FALSE;
    }

    $page = 0;
    $start_page = 0;
    $end_page = -1;
    $searchResults = array();
    $found = array();
    if(isset($_GET['search'])) {
        $results_per_page = 10;
        $pages = 10;
        $page = (isset($_GET['page']) ? $_GET['page'] : 1);
        if($page < 1) {
            $page = 1;
        }
        $start_page = $page - $pages / 2;
        if($start_page < 1) {
            $start_page = 1;
        }
        $end_page = $page + $pages / 2 - 1;

        $searchFor = $_GET['search'];
        $words = array_filter(preg_split("/[\\s]+/", $searchFor), function ($w) { return strlen($w) > 2; });
        $searchJSON = file_get_contents('search.json');
        $searchPages = json_decode($searchJSON, TRUE);
        foreach($searchPages as $searchPage) {
            $foundwords = array();
            if(($title = mfind($searchPage['title'], $searchFor, $words, 10, $foundwords)) !== FALSE) {
                $title['score'] *= (strlen($searchFor) / strlen($searchPage['title']));
            }
            $snippets = array();
            $order = 0;
            foreach($searchPage['texts'] as $text) {
                if(($s = mfind($text['t'], $searchFor, $words, $text['w'], $foundwords)) !== FALSE) {
                    $s['order'] = $order++;
                    $snippets[] = $s;
                }
            }
            if(count(array_diff(array_unique($words), array_unique($foundwords))) == 0) {
                if(count($snippets)) {
                    $len = 300;
                    $snippet_count = intval(($len + 99) / 100);
                    uasort($snippets, 'snipcmp');
                    $original_snippets = $snippets;
                    if(count($snippets) > $snippet_count) {
                        $snippets = array_slice($snippets, 0, $snippet_count);
                    } else {
                        $snippet_count = count($snippets);
                    }
                
                    $snippet_length = intval($len / $snippet_count);
                    $score = 0;
                
                    foreach($original_snippets as $s) {
                        $l = strlen($s['text']);
                        if($l > $snippet_length)
                            $l = $snippet_length;
                        $score += $s['score'] * $s['w'] * ($l / $snippet_length);
                    }
                
                    uasort($snippets, 'ordercmp');
                    if($title !== FALSE) {
                        $score += 30 * $title['score'];
                    }
                    $found[] = array('link' => $searchPage['link'], 'title' => htmlentities($searchPage['title']), 'score' => $score, 'snippets' => $snippets);
                } else if($title !== FALSE) {
                    foreach($searchPage['texts'] as $text) {
                        $s = array('text' => $text['t'], 'w' => $text['w']);
                        $s['score'] = $text['w'];
                        $snippets[] = $s;
                    }
                    $found[] = array('link' => $searchPage['link'], 'title' => htmlentities($searchPage['title']), 'score' => 30 * $title['score'], 'snippets' => $snippets);
                }
            }
        }
        $current_page = $page;
        $end_page = (count($found) + ($results_per_page - 1)) / $results_per_page;
        uasort($found, 'scorecmp');
        $searchResults = array_slice($found, ($page - 1) * $results_per_page, $results_per_page);
    }
?>

<div class="ps1 v1 s1">
<div class="v2 ps2 s20 c1">
<div class="v2 ps2 s20 w1">
<div class="v3 ps3 s3 c73">
<ul class="menu-dropdown-1 v4 ps2 s4 m1" id="m2">
<li class="v2 ps2 s4 mit1">
<div class="menu-content mcv1">
<div class="v5 ps4 s5 c74">
<div class="v2 ps5 s6 c4">
<p class="p1 f1">Menu</p>
</div>
</div>
</div>
<ul class="menu-dropdown v6 ps2 s7 m2" id="m1">
<li class="v2 ps2 s8 mit1">
<a href="index.html" class="ml1"><div class="menu-content mcv2"><div class="v2 ps2 s9 c75"><div class="v2 ps6 s10 c6"><p class="p1 f2">Home</p></div></div></div></a>
</li>
<li class="v2 ps2 s8 mit1">
<div class="menu-content mcv2">
<div class="v2 ps2 s9 c76">
<div class="v2 ps6 s10 c6">
<p class="p1 f2">Members</p>
</div>
</div>
</div>
<ul class="menu-dropdown v7 ps7 s11 m1">
<li class="v2 ps2 s12 mit1">
<a href="faculty.html" class="ml1"><div class="menu-content mcv2"><div class="v2 ps2 s13 c77"><div class="v2 ps6 s14 c6"><p class="p1 f2">Faculty</p></div></div></div></a>
</li>
<li class="v2 ps2 s12 mit1">
<a href="students.html" class="ml1"><div class="menu-content mcv2"><div class="v2 ps2 s13 c78"><div class="v2 ps6 s14 c6"><p class="p1 f2">Students</p></div></div></div></a>
</li>
</ul>
</li>
<li class="v2 ps2 s8 mit1">
<a href="projects.html" class="ml1"><div class="menu-content mcv2"><div class="v2 ps2 s9 c79"><div class="v2 ps6 s10 c6"><p class="p1 f2">Projects</p></div></div></div></a>
</li>
<li class="v2 ps2 s8 mit1">
<a href="publications.html" class="ml1"><div class="menu-content mcv2"><div class="v2 ps2 s9 c80"><div class="v2 ps6 s10 c6"><p class="p1 f2">Publications</p></div></div></div></a>
</li>
<li class="v2 ps2 s8 mit1">
<a href="contact.html" class="ml1"><div class="menu-content mcv2"><div class="v2 ps2 s9 c81"><div class="v2 ps6 s10 c6"><p class="p1 f2">Contact</p></div></div></div></a>
</li>
</ul>
</li>
</ul>
</div>
<div class="v5 ps8 s15 c82"></div>
<div class="v7 ps9 s16 c83"></div>
</div>
</div>
<div class="v2 ps14 s30 c84">
<h2 class="p2 f4">Search results for &quot;<span class="f4"></span>&quot;</h2>
</div>
<div class="v7 ps9 s16 c85"></div>
<div class="v2 ps20 s31 c1">
<div class="v2 ps2 s31 w1">
<div class="v2 ps2 s31 c86">
<?php
    function rev_string(string $string) {
        global $mb;
        $chars = $mb ? mb_str_split($string, 1, mb_internal_encoding()) : str_split($string, 1);
        return implode('', array_reverse($chars));
    }

    function word_trunc($string, $length) {
        global $mb;
        if(strlen($string) > $length)
        {
            $string = wordwrap($string, $length);
            $string = $mb? mb_substr($string, 0, mb_strpos($string, "\n")) : substr($string, 0, strpos($string, "\n"));;
        }
        return $string;
    }

    function clip_string($string, $pos, $length, $total) {
        global $mb;
        $m = $mb ? mb_substr($string, $pos, $length) : substr($string, $pos, $length);
        $before = $mb ? mb_substr($string, 0, $pos) : substr($string, 0, $pos);
        $after = $mb ? mb_substr($string, $pos + $length, mb_strlen($string) - ($pos + $length)) : substr($string, $pos + $length, strlen($string) - ($pos + $length));
        $before = rev_string($before);
        $half = intval(($total - $length) / 2);
        $hlPre = '';
        $hlPost = '';
        $out = htmlentities(rev_string(word_trunc($before, $half))) . $hlPre . htmlentities($m) . $hlPost . htmlentities(word_trunc($after, $half));
        return $out;
    }

    if(count($searchResults) == 0) {
        $result = '<div class="v2 ps21 s32 c87"><div class="v2 ps22 s33 c88"><p class="p2 f12">{title}</p></div><div class="v2 ps22 s34 c89"><p class="p2 f12">{text}</p></div></div>';
        $result = str_replace('{title}', htmlentities('No search result'), $result);
        $result = str_replace('{text}', '', $result);
        echo $result;
    }
    else {
        foreach($searchResults as $searchResult) {
            $result = '<div class="v2 ps21 s32 c87"><div class="v2 ps22 s33 c88"><p class="p2 f12">{title}</p></div><div class="v2 ps22 s34 c89"><p class="p2 f12">{text}</p></div></div>';
            $result = str_replace('{title}', '<a href="' . $searchResult['link'] . '">' . $searchResult['title'] . '</a>', $result);

            $text = "";
            foreach($searchResult['snippets'] as $s) {
                if(isset($s['pos'])) {
                    $pos = $s['pos'];
                    $m = $s['matchlen'];
                    $snippet = clip_string($s['text'], $pos, $m, $snippet_length);
                } else {
                    $snippet = $s['text'];
                }
                if(strlen($text))
                    $text .= "&hellip;";
                $text .= " " . $snippet;
            }

            $result = str_replace('{text}', $text, $result);
            echo $result;
        }
   }
?>

</div>
<div class="v7 ps9 s16 c90"></div>
<div class="v7 ps9 s16 c91"></div>
<div class="v7 ps9 s16 c92"></div>
</div>
</div>
<div class="v7 ps9 s16 c93"></div>
<div class="v2 ps24 s35 c94">
<div class="ps23">
<?php

    $control = '<div class="v2 ps2 s36 c95" style="display:none"><a href="#" class="f13 btn1 v8 s37">&lt;&lt;</a></div>';
    if($page > 1) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . ($page - 1);
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        $control = str_replace('href="#"', 'href="' . $url . '"', $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c70" style="display:none"><a href="#" class="f13 btn2 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 1 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c96" style="display:none"><a href="#" class="f13 btn3 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 2 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c97" style="display:none"><a href="#" class="f13 btn4 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 3 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c98" style="display:none"><a href="#" class="f13 btn5 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 4 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c23" style="display:none"><a href="#" class="f13 btn6 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 5 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c56" style="display:none"><a href="#" class="f13 btn7 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 6 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c99" style="display:none"><a href="#" class="f13 btn8 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 7 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c2" style="display:none"><a href="#" class="f13 btn9 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 8 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c100" style="display:none"><a href="#" class="f13 btn10 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 9 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c101" style="display:none"><a href="#" class="f13 btn11 v8 s37">{page_num}</a></div>';
    $buttonPage = $start_page + 10 - 1;
    if($buttonPage <= $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . $buttonPage;
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        if($buttonPage == $page) {
            $control = str_replace('href="#"', 'style="border: 0; background-color: #009dd9; color: #fff; border-color: #677a85"', $control);
        }
        else {
            $control = str_replace('href="#"', 'href="' . $url . '"', $control);
        }
        $control = str_replace('{page_num}', $buttonPage, $control);
    }
    echo $control;

?>

<?php

    $control = '<div class="v2 ps25 s36 c102" style="display:none"><a href="#" class="f13 btn12 v8 s37">&gt;&gt;</a></div>';
    if($page < $end_page) {
        $url = strtok($_SERVER['REQUEST_URI'],'?') . '?search=' . $searchFor . '&page=' . ($page + 1);
        $control = str_replace('style="visibility:hidden"', '', $control);
        $control = str_replace('style="display:none"', '', $control);
        $control = str_replace('href="#"', 'href="' . $url . '"', $control);
    }
    echo $control;

?>

</div>
</div>
</div>
<div class="menu-device"></div>
<script>dpth="/"</script>
<script type="text/javascript" src="js/jquery.f44976.js"></script>
<script type="text/javascript" src="js/jqueryui.f44976.js"></script>
<script type="text/javascript" src="js/menu.f44976.js"></script>
<script type="text/javascript" src="js/menu-dropdown-animations.f44976.js"></script>
<script type="text/javascript" src="js/menu-dropdown.92c872.js"></script>
<script type="text/javascript" src="js/menu-dropdown-1.92c872.js"></script>
<script type="text/javascript" src="js/search.92c872.js"></script>
<script type="text/javascript">
var ver=RegExp(/Mozilla\/5\.0 \(Linux; .; Android ([\d.]+)/).exec(navigator.userAgent);if(ver&&parseFloat(ver[1])<5){document.getElementsByTagName('body')[0].className+=' whitespacefix';}
</script>
</body>
</html>