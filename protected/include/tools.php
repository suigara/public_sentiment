<?php
/**
 * Created by PhpStorm.
 * User: wangweiu
 * Date: 2015/3/3
 * Time: 9:05
 */
/**
 * @param $keyword
 * @return string
 */
function UTF8_to_gb2312($keyword)
{
    $keyword = mb_convert_encoding($keyword, "gb2312", "UTF-8");
    return $keyword;
}

function gb2312_to_UTF8($keyword)
{
    $keyword = mb_convert_encoding($keyword, "UTF-8", "gb2312");
    return $keyword;
}