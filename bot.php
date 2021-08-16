<?php
    include('WSCTW_CompetitionTopics_Crawler.php');

    date_default_timezone_set("Asia/Taipei");

    ini_set('user_agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36');
    $topic_url = "https://www.wdasec.gov.tw/News_Content.aspx?n=12FE9C104388A457&s=3D42933DA696DA8C";

    $logs_file = file_get_contents(dirname(__FILE__) . "/log.json");
    $logs = json_decode($logs_file, true);

    $flarum_api_url = "";
    $flarum_api_token = "";
    $post_id = 0;
    $discussions_id = 0;

    $topics = array();
    $new_topics = array();
    printT("正在從勞動部網站爬取最新的技能競賽試題...");
    $topics = getCompetitionTopics($topic_url);
    if($topics) {
        echo "\033[33m[成功]\033[0m" . PHP_EOL;
        $topics_count = count($topics);
        $n = 0;
        foreach($topics as $topic) {
            println("獲取到最新題目：" . $topic['name']);
            $files_count = count($topic['files']);
            $i = 1;
            foreach($topic['files'] as $file) {
                println("→檔案附件[" . $i . "/" . $files_count . "]：(" . $file['type'] . ") " . $file['link']);
                $i++;
            }
            $topicInLogs = searchNameInArray($topic['name'], $logs);
            if($logs[$topicInLogs] != $topic or $logs[$topicInLogs]['files'] != $topic['files']) {
                println("獲取到更新題目：" . $topic['name']);
                array_push($new_topics, $topics[$n]);
            }
            $n++;
        }

        // var_dump($topics); //DEBUG
        // var_dump($new_topics); //DEBUG

        if(count($topics) > 0) {
            $post = "### 本文章長期更新，有興趣的用戶請關注此文章\n";
            $post .= ">**更新時間：" . date("Y/m/d H:i:s") . "**\n";
            $post .= "題目來源：[連結]" . "(" . $topic_url . ")";
            $post .= "\n\n";
            $post .= "名稱 | 題目檔案\n--- | ---\n";

            $skills = $topics;
            foreach($skills as $skill) {
                $post .= $skill['name'];
                foreach($skill['files'] as $file) {
                    $post .= " | " . "[" . $file['type'] . "]" . "(" . $file['link'] . ")";
                }
                $post .= "\n";
            }

            // echo $post; //DEBUG
            editFlarumPost($post_id, $post);

            if(count($new_topics) > 0) {
                $post = ">**偵測到以下題目有更新**";
                $post .= "\n\n";
                $post .= "名稱 | 題目檔案\n--- | ---\n";
                $skills = $new_topics;
                foreach($skills as $skill) {
                    $post .= $skill['name'];
                    foreach($skill['files'] as $file) {
                        $post .= " | " . "[" . $file['type'] . "]" . "(" . $file['link'] . ")";
                    }
                    $post .= "\n";
                }
                postNewPost($discussions_id, $post);
            }

            //保存該次爬取紀錄
            if($fp = fopen(dirname(__FILE__) . '/log.json','w+')) {  
                $rc = fwrite($fp, json_encode($topics)); 
                fclose($fp); 
            }
        }


    }else{
        echo "\033[31m[失敗]\033[0m" . PHP_EOL;
    }

    function println($string) {
        printT( $string . PHP_EOL );
    }

    function printT($string) {
        echo "\033[33m" . date("[Y/m/d H:i:s]") . "\033[0m " . $string;
    }

    function searchNameInArray($name, $array) {
        if(is_array($array)) {
            $i = 0;
            foreach($array as $array_data) {
                if($array_data['name'] == $name) return $i;
                $i++;
            }
        }
        return false;
    }

    function postNewPost($discussions_id, $content) {
        global $flarum_api_url, $flarum_api_token;
        $post_data = json_decode('{ "data": { "type": "posts", "attributes": { "contentType": "comment", "content": "test", "canEdit": true, "canDelete": true, "canHide": true, "canFlag": false, "isApproved": true, "canApprove": true, "canLike": true }, "relationships": { "discussion": { "data": { "type": "discussions", "id": "11" } } } }, "included": [{ "type": "discussions", "id": "11" }] }', true);
        $post_data['data']['relationships']['discussion']['d']['data']['id'] = $discussions_id;
        $post_data['included'][0]['id'] = $discussions_id;
        $post_data['data']['attributes']['content'] = $content;
        $url = $flarum_api_url . "/posts";
        curlPost($url, $flarum_api_token, $post_data);
    }

    function editFlarumPost($post_id, $content) {
        global $flarum_api_url, $flarum_api_token;
        $url = $flarum_api_url . "/posts/" . $post_id;
        $data = array();
        $data['data']['attributes']['content'] = $content;
        $time = time();
        $data['data']['attributes']['editedAt'] = gmdate("Y-m-d", $time) . 'T' . gmdate("H:i:s", $time) . '+00:00';
        curlPatch($url, $flarum_api_token, $data);
    }

    function curlPost($url, $token, $data){
        $data  = json_encode($data);
        $headerArray = array(
            "Content-type:application/json;charset='utf-8'",
            "Accept:application/json",
            'Authorization: Token '. $token
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headerArray);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return json_decode($output, true);
    }

    function curlPatch($url, $token, $data){
        $data  = json_encode($data);
        $ch = curl_init();
        curl_setopt ($ch,CURLOPT_URL,$url);
        $headers = array(
            'Content-Type:application/json',
            'Authorization: Token '. $token
        );
        curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        $output = curl_exec($ch);
        curl_close($ch);
        $output = json_decode($output);
        return $output;
    }
?>