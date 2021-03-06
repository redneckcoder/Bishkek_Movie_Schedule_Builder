<?php
/*
Copyright (c) 2013 redneckcoder@namba.kg
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
require_once('DB.php');

function curl_get_string($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_VERBOSE, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_REFERER, "");
  curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}

function get_data_string($filename, $url) {
  $currentTimestamp = time();
  if (file_exists($filename) && ($currentTimestamp - filemtime($filename)) < 4500) {
    $st = file_get_contents($filename);
  } else {
    $st = curl_get_string($url);
    $st = get_stripped_xml_data($st, $filename);
    file_put_contents($filename, $st);
  }
  return $st;
}

function get_stripped_xml_data($st, $filename) {
  $b = strpos($st, '<head>');
  $e = strpos($st, '</head>');
  $st = substr($st, $b, $e - $b + 7);
  return $st;
}

function parse_to_correct_price($st) {
  if (preg_match('/([\d\-]+)\s(.+)/', $st, $matches)) {
    $price = $matches[1];
    return $price;
  }
  return false;
}

$data = null;
$st = get_data_string('/tmp/cache_cinema', 'http://cinema.kg/');
if (preg_match('/new cinema\(([^\)]+)\)/', $st, $matches)) {
  $data = json_decode($matches[1]);
}

if ($data) {
  try {
    $db = new DB();
    $cinemaName = 'Россия';

    foreach ($data->timetable as $dayInTable) {
      foreach ($dayInTable as $movieInfo) {
        $movieId = $movieInfo->movieId;
        $dataArray = array();
        $dataArray['cinemaName'] = $cinemaName;
        $dataArray['movieDate'] = date('Y-m-d', $movieInfo->timestamp / 1000);
        $dataArray['movieName'] = $data->movies->{$movieId}->title;
        $dataArray['movieHall'] = $movieInfo->hall;
        $dataArray['moviePrice'] = $movieInfo->price;
        $dataArray['movieTime'] = $movieInfo->time;
        $dataArray['movieLink'] = 'http://cinema.kg/ru/movies/?id=' . $movieId;
        $db->insert($dataArray);
      }
    }
    $db->close();
  } catch (Exception $e) {
    echo($e);
  }
}
