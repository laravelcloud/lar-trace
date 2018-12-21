<?php

/**
 * 多维数组压缩为一维
 * @param $array 原始数据
 * @param string $prefix 前缀
 * @return array
 */
function lar_array_dot(array $array, string $prefix = '') : array
{
    $results = [];

    foreach ($array as $key => $value) {
        if (is_array($value) && ! empty($value)) {
            $results = array_merge($results, lar_array_dot($value, $prefix . $key . '.'));
        } else {
            $results[$prefix . $key] = $value;
        }
    }

    return $results;
}