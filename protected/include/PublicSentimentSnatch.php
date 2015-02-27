<?php
//ini_set("error_reporting","E_ALL & ~E_NOTICE");
/**
 * Created by PhpStorm.
 * User: 伟
 * Date: 2015/2/25
 * Time: 11:42
 */
ini_set('date.timezone', 'Asia/Shanghai');

define("YQ_URL", "http://zhishu.sogou.com/");

include("simple_html_dom.php");

class PublicSentimentSnatch
{

    function snatchToDb($keyword,$title,$url)
    {
        $db = Mod::app()->db;
        $querySql = 'select id from snatch_data where keyword=? and url=?';
        $count = $db->createCommand($querySql)->query(array($keyword,$url))->count();
        if($count > 0){
            return;
        }
        $sql = 'insert into snatch_data(keyword,tilte,url,ts) values (?,?,?,?)';
        $db->createCommand($sql)->execute(array($keyword,$title,$url,date('Y-m-d H:i:s',time())));
    }

    function  snatchMoreNews($keyword,$pageNo=1)
    {
        $utf8KeyWord = $keyword;
        $keyword = $this->UTF8_to_gb2312($keyword);
        $moreurl = "http://news.sogou.com/news?query=" . $keyword."&page=".$pageNo;

        $html = file_get_html($moreurl);
        foreach ($html->find('div[class=rb]') as $e) {
            $a =$e->find('a',0);
            $url = $a->href.' ';
            $title = $this->gb2312_toUTF8($a->innertext);
            $this->snatchToDb($utf8KeyWord,$title,$url);
        }
        $nextPage = $html->find('a[id=sogou_next]');
        if($nextPage){
            $this->snatchMoreNews($utf8KeyWord,$pageNo + 1);
        }

    }
    function getPublicSentimentJsonLastMonth($keywordUtf8){
        $yesterday = date("Y-m-d 00:00:00",strtotime("-1 day"));
        $lastMonth = date("Y-m-d 00:00:00",strtotime("last month"));
        return $this->getPublicSentimentJsonByPeriod($keywordUtf8,$lastMonth,$yesterday);
    }
    function getPublicSentimentJsonByPeriod($keyword,$startDate,$endDate)
    {
        $keyword = $this->UTF8_to_gb2312($keyword);
        $json = $this->getAllDataJson($keyword);
        $userIndexes = explode(',', $json['userIndexes']);
        $periods = explode('|', $json['period']);
        if (!$periods) {
            return "";
        }
        $start = $periods[0];
        $end = $periods[1];
        $dateIdx = $this->prDates($start, $end);
        echo $startDate . ' to ' . $endDate . '<br>';
        $pointNews = $this->getPointNews($keyword, $startDate, $endDate, $userIndexes);
        echo json_encode($pointNews) . '<br>';
        foreach ($pointNews["relatednews"] as $currentnews) {
        if ($currentnews) {
            echo $currentnews['title'] . '-' . $currentnews['date'] . '<br>';
        }
    }
        return $pointNews;

    }

    function prDates($start, $end)
    {
        $dt_start = strtotime($start);
        $dt_end = strtotime($end);
        $index = 0;
        while ($dt_start <= $dt_end) {
            $result[date('Y-m-d', $dt_start)] = $index;
            //echo date('Y-m-d',$dt_start)."=".$index."\n";
            $dt_start = strtotime('+1 day', $dt_start);
            $index++;
        }
        return $result;
    }

    function getPointNews($keyword, $startDate,
                          $endDate, $userIndexes)
    {
       // echo strtotime($startDate);
        $pointtime = $this->getTopTen($userIndexes, strtotime($startDate),
            strtotime($endDate), strtotime($endDate) * 1000);
        $domain = "trend/idx_news.jsp";
        $param = "nosplit=1&ie=utf8&from=newsajaj&type=query&qnewstype=point&query="
            . urlencode($this->gb2312_toUTF8($keyword)) . "&newstype=-1&trend=-1&mode=2"
            . "&fdate="
            . strtotime($startDate)
            . "&pointtime="
            . $pointtime
            . "&tdate="
            . strtotime($endDate)
            . "&area=all&qtype=0&clusterid=&ind=0";
        $newsURL = YQ_URL . $domain . "?" . $param;
        $newsJson = $this->getJson($newsURL);
        return $newsJson;
    }

    function getTopTen($data_arr, $stime, $etime, $endtime)
    {

        $behind_days = 0;
        if ($endtime / 1000 > $etime)
            $behind_days = ($endtime / 1000 - ($endtime / 1000 - $etime) % 86400 - $etime) / 86400;
        $whole_days = ($etime - $stime) / 86400;
        // echo '$whole_days='.($etime - $stime).'<br>';
        $len = count($data_arr);
        $end = $len - $behind_days - 1;
        $begin = $end - $whole_days;
        $remainder = $whole_days % 10;
        $numbers = ($whole_days - $remainder) / 10;
        $fseconds = $stime;
        $pointsst = "";
        if ($begin < 0)
            $begin = 0;
        if ($len < 10) {
            return "";
        }

        for ($i = $begin, $k = 0; $i < $end && $k < 10;) {
            $max = 0;
            $num = 0;
            $loc = 0;
            $nums = $numbers;
            if ($k > 10 - $remainder - 1)
                $nums += 1;
            for ($j = 0; $j < $nums; $j++) {
                $num = $data_arr[$i + $j];
                //echo 'num='.($nums).'<br>';
                if ($num > $max) {
                    $max = $num;
                    $loc = $j;
                }
            }
            $fseconds += 86400 * $loc;
            $pointSec = $fseconds + 43200;
            if ($pointSec > $etime)
                $pointSec -= 86400;
            $pointsst = $pointsst . $pointSec;
            //echo date('Y-m-d', $pointSec).'<br>';

            $pointsst = $pointsst . ",";
            $fseconds += 86400 * ($nums - $loc);
            $i += $nums;
            $k++;
        }
        return $pointsst;
    }

    function getJson($url)
    {

        //$postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'GET',// GET/POST
                'header' => 'Content-type:application/x-www-form-urlencoded',
                //'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $result = $this->gb2312_toUTF8($result);
        $end = strpos($result, ';');
        $result = substr($result, 0, $end);
        $json = json_decode($result, true);
        //echo json_last_error();
        return $json;
    }

    function getAllDataJson($keyword)
    {
        $url = YQ_URL . "sidx?type=0&query=" . $keyword . "&newstype=-1";

        $html = file_get_html($url);
        $script_text = "";
        foreach ($html->find('script') as $e) {
            $current_script = $this->gb2312_toUTF8($e->innertext);
            //echo $script_text;
            if (strpos($current_script, "var tmp") > -1) {
                $script_text = $current_script;
                //return $script_text;
            }
        }
        if (strlen($script_text) > 0) {
            $startStr = "tmp={";
            $start = strpos($script_text, $startStr);
            $script_text = substr($script_text, $start + strlen($startStr) - 1);
            $end = strpos($script_text, ';');
            $script_text = substr($script_text, 0, $end);
            $json = json_decode($script_text, true);
            // echo $script_text . '</br>';
            // $userIndexes=explode(',',$json['userIndexes']);
            return $json;
        }
        return "";
    }

    /**
     * @param $keyword
     * @return string
     */
    public function UTF8_to_gb2312($keyword)
    {
        $keyword = mb_convert_encoding($keyword, "gb2312", "UTF-8");
        return $keyword;
    }

    public function gb2312_toUTF8($keyword)
    {
        $keyword = mb_convert_encoding($keyword, "UTF-8", "gb2312");
        return $keyword;
    }

} 