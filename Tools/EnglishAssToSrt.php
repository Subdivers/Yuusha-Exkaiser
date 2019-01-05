<?php
/*
 * usage: just run this script and it will split all the ASS files in the working directory into SRT and ASS files in respective languages.
 *
 * sorry for write once read never code
 *
 * maybe i'll fix this file sometime  later
 */

function strip_ass($text){
    if(is_array($text))
        $text=$text['text'];
    return preg_replace('/{.*?}/',"",$text);
}
function decodetime($s){
    sscanf($s, "%d:%d:%d.%d", $v1, $v2, $v3, $v4);
    return $v1 * 3600000 + $v2 * 60000 + $v3 * 1000 + $v4 * 10;
}
function encodetime($t){
    return sprintf("%01d:%02d:%02d.%02d",intval($t / 3600 / 1000), (intval($t / 60000) % 60), (intval($t / 1000) % 60), ($t % 1000) / 10);
}
function encodesrttime($t){
    return sprintf("%01d:%02d:%02d.%03d",intval($t / 3600 / 1000), (intval($t / 60000) % 60), (intval($t / 1000) % 60), ($t % 1000));
}
function is_song($style){
    if(is_array($style))
        $style=$style['style'];
    return 0 !== preg_match("/^song.*$/ui", $style);
}
function utxt($ii){
    $radix = strlen($stritoa="0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-_");
    $rc = "";
    do{
        $rc .= $stritoa[ $ii % $radix ];
        $ii = floor( $ii / $radix );
    }while($ii>0);
    return strrev($rc);
}
function invalid_error(){
    echo "Error";
}
function remove_fade($_data){
    $_idx = 0;
    $data = array();
    $kara_weight=array("SoDiveTr" => array(1, 0), "FightersTr" => array(3, -1));
    foreach($_data as $val){
        if(is_song($val)){
            $val["text"] = "♪".$val["text"]."♩";
        }
        for($i=0; $i<=11; $i++) unset($val[$i]);
        $val['index'] = $_idx++;
        $data[$val["style"]][] = $val;
    }
    $_data = $data;
    $data = array();
    foreach($_data as &$val){
        usort($val, function($a, $b){
            if(0 != ($res=strcmp($a['start'], $b['start'])))
                return $res;
            return strcmp($a['end'], $b['end']);
        });
        $last_end = $last_fade_end = $last_reference = 0;
        for($i=0; $i<count($val); $i++){
            $fade_pre = $fade_post = 0;
            $start = decodetime($val[$i]['start']);
            $end = decodetime($val[$i]['end']);
            if(!isset($val[$i+1]) || !isset($val[$i])) continue;
            while($val[$i]['end']==$val[$i+1]['start'] && strip_ass($val[$i]) == strip_ass($val[$i+1])){
                $val[$i]['end']=$val[$i+1]['end'];
                array_splice($val, $i+1, 1);
                if(!isset($val[$i+1]) || !isset($val[$i])) break;
            }
            if(!isset($val[$i+1]) || !isset($val[$i])) continue;
            if($val[$i]['start'] == $val[$i+1]['start'] && $val[$i]['end'] == $val[$i+1]['end']){
                if(strip_ass($val[$i]) == strip_ass($val[$i+1])){
                    array_splice($val, $i, 1);
                    $i--;
                }
                continue;
            }
            if (!preg_match('/{[^}]*\\\\fad(e?)\(([0-9]+),([0-9]+)(?:,[0-9]+,([0-9]+),([0-9]+),([0-9]+),([0-9]+))?\)[^}]*}/im', $val[$i]["text"], $regs)){
                $last_reference = $i;
                continue;
            }
            if($regs[1] === 'e' && isset($regs[4])){
                $start += intval($regs[4]);
                $fade_pre = intval($regs[5]) - intval($regs[4]);
                $end = $start + intval($regs[7]);
                $fade_post = intval($regs[7]) - intval($regs[6]);
                $val[$i]['end']=encodetime($end);
            }else{
                $fade_pre = intval($regs[2]);
                $fade_post = intval($regs[3]);
            }
            
            if($last_end > $start){
                $fade_length = $start + $fade_pre - $last_fade_end;
                $weight = isset($kara_weight[$val[$i]['style']]) ? $kara_weight[$val[$i]['style']] : array(3, 1);
                $val[$i]['start']=encodetime($last_fade_end + ($fade_length * $weight[1] / $weight[0]));
                for($j=$last_reference; $j<$i; $j++)
                    $val[$j]['end']=$val[$i]['start'];
            }
            
            $last_reference = $i;
            
            $last_fade_end = $end - $fade_post;
            $last_end = $end;
        }
        $data = array_merge($data, $val);
    }
    unset($val, $_data);
    usort($data, function($a, $b){
        if(0 != ($res=strcmp($a['start'], $b['start'])))
            return $res;
        if(0 != ($res=strcmp($a['end'], $b['end'])))
            return $res;
        return $a['index']-$b['index'];
    });
    array_filter($data, function($a){ return strlen(trim(strip_ass($a['text'])))>0; });
    return $data;
}
function srtfy($data){
    $data = remove_fade($data);
    $timecodes = array();
    foreach($data as $t){
        $timecodes[$t["start"]]=1;
        $timecodes[$t["end"]]=1;
    }
    ksort($timecodes);
    $timecodes = array_keys($timecodes);
    
    $srt="";
    
    $i = 1;
    foreach($timecodes as $k => $time){
        $text = $prt = "";
        if(!isset($timecodes[$k+1])) continue;
        $etime = $timecodes[$k+1];
        $cline = array();
        foreach($data as $line){
            if($prt === preg_replace( '|{[^}]*}|', "", $line["text"])) continue;
            if(strcmp($time,$line["start"]) >= 0 && strcmp($time,$line["end"]) < 0){
                $cline[] = $line;
                $prt = preg_replace( '|{[^}]*}|', "", $line["text"]);
            }
            if(strcmp($time,$line["end"]) < 0 && (!isset($etime) || strcmp($etime, $line["end"]) > 0))
                $etime = $line["end"];
            if(strcmp($time,$line["start"]) < 0 && (!isset($etime) || strcmp($etime, $line["start"]) > 0))
                $etime = $line["start"];
        }
        
        if(count($cline) == 0)
            continue;
        $line_prio = array();
        $only_default = true;
        foreach($cline as $line){
            if($line['style'] !== 'Default' || substr($line['text'],0,1)==='-')
                $only_default = false;
            if(is_song($line)){
                if(preg_match('/{[^*]*\\\\pos\(([0-9]+),([0-9]+)\)[^*]*}/im', $line["text"], $regs))
                    $line_prio[2][100010+intval($regs[2])][intval($regs[1])][] = $line["text"];
                else
                    $line_prio[2][0][0][] = $line["text"];
            }else if(preg_match('/{[^*]*\\\\pos\(([0-9]+),([0-9]+)\)[^*]*}/im', $line["text"], $regs)) {
                $line_prio[0][intval($regs[2])][intval($regs[1])][] = $line["text"];
            } else {
                $an = 2;
                if (preg_match('/{[^}]*\\\\an([0-9]+)[^}]*?}/im', $line["text"], $regs)) {
                    $an = intval($regs[1]);
                    $only_default=false;
                }
                $line_prio[1][100000+$an][0][] = $line["text"];
            }
        }
        ksort($line_prio);
        foreach($line_prio as $prio){
            ksort($prio);
            foreach($prio as $ypos => $ly){
                $cl = array();
                ksort($ly);
                foreach($ly as $lx)
                    $cl[]= implode($ypos >= 100000 ? "\r\n" : " / ", $lx);
                $cl = implode(": ", $cl);
                $text .= $cl . "\r\n";
            }
        }
        $text = explode("\r\n", $text);
        $arr = array(array(),array());
        
        foreach($text as $val){
            $val = trim(str_replace("</i>: <i>", ": ", $val));
            if($val === "") continue;
            if(false !== strpos($val,"<i>") && $val != "<i></i>")
                $arr[1][] = $val;
            else
                $arr[0][] = $val;
        }
        if(count($arr[0]) == 0){
            $text = implode("\r\n", $arr[1]);
        }else{
            $text = implode("\r\n", $arr[1]);
            $text2 = implode("\r\n", $arr[0]);
            if($text !== "" && $text2 !== "")
                $text = "$text2\r\n<i></i>\r\n$text";
            else if($text === "")
                $text = $text2;
        }
        
        $time_s = str_replace(".",",",$time)."0";
        $time_e = str_replace(".",",",$etime)."0";
        $text = preg_replace_callback( '|{[^}]*}|', function ($m) {
            $m = strtolower($m[0]);
            if(false !== strpos($m, "\\i1")) return "<i>";
            if(false !== strpos($m, "\\i0")) return "</i>";
            if(false !== strpos($m, "\\i")) return "</i>";
            return "";
        }, str_replace("\\N", "\r\n", trim($text)));
        $text = str_replace("<i>", "", $text);
        $text = str_replace("</i>", "", $text);
        $text = preg_replace('/♪+/u', '♪', $text);
        $text = preg_replace('/♩+/u', '♩', $text);
        $text = str_replace('♪♩', '', $text);
        $text = str_replace('♩♪', '', $text);
        $text = str_replace('♪', '♪ ', $text);
        $text = str_replace('♩', ' ♩', $text);
        $text = trim(preg_replace('/(\r\n)+/', "\r\n", $text));
        if(strlen($text)===0) continue;
        $srt .= $i++. "\r\n$time_s --> $time_e\r\n$text\r\n\r\n";
    }
    return $srt;
}
function decodeasstime($s){
    sscanf($s, "%d:%d:%d.%d", $v1, $v2, $v3, $v4);
    return $v1 * 3600000 + $v2 * 60000 + $v3 * 1000 + $v4 * 10;
}
function encodeasstime($t){
    return sprintf("%01d:%02d:%02d.%02d",intval($t / 3600 / 1000), (intval($t / 60000) % 60), (intval($t / 1000) % 60), ($t % 1000) / 10);
}
function parse_ass($data){
    preg_match_all('/^(?<linetype>Comment|Dialogue):\s*(?<layer>[^,]*?)\s*,\s*(?<start>[^,]*?)\s*,\s*(?<end>[^,]*?)\s*,\s*(?<style>[^,]*?)\s*,\s*(?<name>[^,]*?)\s*,\s*(?<marginl>[^,]*?)\s*,\s*(?<marginr>[^,]*?)\s*,\s*(?<marginv>[^,]*?)\s*,\s*(?<effect>[^,]*?)\s*,\s*(?<text>.*?)\s*$/uim', $data, $res, PREG_SET_ORDER);
    foreach($res as &$line){
        $line['start'] = decodeasstime($line['start']);
        $line['end'] = decodeasstime($line['end']);
        $line['length'] = $line['end'] - $line['start'];
    }
    return $res;
}
function d($ass, $target_dir){
    $input = file_get_contents($ass);
    $input = substr($input, strpos($input, "\n[Events]")+1);
    
    $lines_srt=[];
    
    foreach(explode("\n", $input) as $line){
        if(!preg_match('/^(?<linetype>Dialogue):\s*(?<layer>[^,]*?)\s*,\s*(?<start>[^,]*?)\s*,\s*(?<end>[^,]*?)\s*,\s*(?<style>[^,]*?)\s*,\s*(?<name>[^,]*?)\s*,\s*(?<marginl>[^,]*?)\s*,\s*(?<marginr>[^,]*?)\s*,\s*(?<marginv>[^,]*?)\s*,\s*(?<effect>[^,]*?)\s*,\s*(?<text>.*?)\s*$/uim', $line, $res))
            continue;
        
        $res['text'] = preg_replace('/\\\\p1([^}]*)}(.*?)(?:$|{([^}]*)\\\\p0)/im', '$1$3', $res['text'] . '{\\p0}');
        $lines_srt[]=$res;
    }
    $basename = basename($ass);
    file_put_contents("$target_dir/en/".substr($basename, 0, -4) . ".srt", srtfy($lines_srt));
}
function appendcrc($f, $f2){
    $hash=hash_file('crc32b', $f);
    $hash=strtoupper(substr("0000000$hash",-8,8));
    $f2 = substr($f2, 0, strpos($f2, ".")) . "[$hash].mkv";
    rename($f, $f2);
}

system("chcp 949");

$source_dir = realpath(__DIR__ . "/../English Subtitles/") . DIRECTORY_SEPARATOR;
$video_dir = realpath(__DIR__ . "/../Videos/") . DIRECTORY_SEPARATOR;
$target_dir = realpath(__DIR__ . "/../Temp/") . DIRECTORY_SEPARATOR;

echo <<<TEXT
Source: $source_dir
Video: $video_dir
Target: $target_dir

TEXT;

$dh = opendir($source_dir);
while (false !== ($f = readdir($dh))) {
    if (strtolower(substr($f, -4, 4) !== ".ass")) continue;
    if (false !== strpos(substr($f, 0, -4), ".")) continue;
    echo "Working on $f...\n";
    
    $title = explode(".", trim(substr($f, strpos($f, "-") + 1)))[0];
    
    d($source_dir . $f, $target_dir);
    
    continue;
    
    $mkv = $video_dir . substr($f, 0, -4) . ".mkv";
    if (!file_exists($mkv)) continue;
    
    $assfn = substr($f, 0, -4) . ".ass";
    $srtfn = substr($f, 0, -4) . ".srt";
    $subs = [];
    
    if (false !== strpos(file_get_contents("{$target_dir}en\\$assfn"), "{"))
        $subs[] =
            "--sub-charset 0:utf-8 --language 0:eng --track-name 0:\"English ASS\" \"{$target_dir}en\\$assfn\" " .
            "--sub-charset 0:utf-8 --language 0:kor --track-name 0:\"한국어 ASS\" \"{$target_dir}ko\\$assfn\" " .
            "--sub-charset 0:utf-8 --language 0:eng --track-name 0:\"English SRT\" \"{$target_dir}en\\$srtfn\" " .
            "--sub-charset 0:utf-8 --language 0:kor --track-name 0:\"한국어 SRT\" \"{$target_dir}ko\\$srtfn\" ";
    else
        $subs[] =
            "--sub-charset 0:utf-8 --language 0:eng --track-name 0:\"English\" \"{$target_dir}en\\$srtfn\" " .
            "--sub-charset 0:utf-8 --language 0:kor --track-name 0:\"한국어\" \"{$target_dir}ko\\$srtfn\" ";
    
    $cmd="\"C:\\Program Files\\MKVToolNix\\mkvmerge.exe\" " .
        "-o \"{$target_dir}_tmp.mkv\" " .
        "--title \"$title\" ".
        "--attachment-mime-type font/ttf --attach-file C:\\Windows\\Fonts\\candarab.ttf ".
        "--attachment-mime-type font/ttf --attach-file C:\\Windows\\Fonts\\candaraz.ttf ".
        "--language 0:jpn --language 1:jpn \"$mkv\" ".
        implode(" ", $subs);
    $cmd=iconv("utf-8", "euc-kr", $cmd);
    echo $cmd."\n";
    system($cmd);
    $hash=hash_file('crc32b', "{$target_dir}_tmp.mkv");
    $hash=strtoupper(substr("0000000$hash",-8,8));
    rename("{$target_dir}_tmp.mkv", $target_dir . substr($f, 0, strpos($f, ".")) . "[$hash].mkv");
}
