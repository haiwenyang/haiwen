<?php
define("TOKEN", "hewen");
$sise = new sise();
if (!isset($_GET['echostr'])) {
    $sise->responseMsg();
}else{
    $sise->valid();
}

class sise{

    //验证签名
    public function valid(){
        $echoStr = $_GET["echostr"];
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if($tmpStr == $signature){
            ob_clean();
            echo $echoStr;
            exit;
        }
    }

    public function responseMsg(){
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);

            //消息类型分离
            switch ($RX_TYPE) {
                case "event":
                    $result = $this->receiveEvent($postObj);
                    break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
                default:
                    $content = "暂时不识别此类型：".$RX_TYPE;
                    $result = $this->transmitText($postObj,$content);
                    break;
            }
            echo $result;
        }else {
            echo "";
            exit;
        }
    }

    //接收事件消息
    private function receiveEvent($object){
        $content = "";
        $db = $this->connection();
        $sql = "";
        switch ($object->Event) {
            case "subscribe":
                $content = "欢迎关注sise关键\n\n回复“功能”查看更多功能";
                if($this->checkUser($object) == 0){
                    $sql = "insert into user(id,wechat,account,password) VALUES (NULL,:wechat,NULL,NULL)";

                }
                break;
            case "unsubscribe":
                $sql = "DELETE FROM user WHERE wechat=:wechat";
                break;
        }
        $stmt = $db->prepare($sql);//预处理，返回一个PDOStatement类对象
        
        $stmt->bindParam(':wechat',$object->FromUserName);//绑定参数，对于命名参数占位符，绑定:wechat参数
        $stmt->execute();
        $res = $stmt->rowCount();
        $content = "欢迎关注sise关键\n\n回复“功能”查看更多功能66".$res;
        $result = $this->transmitText($object, $content);
        $db = null;
        return $result;
    }

    //接收文本消息
    private function receiveText($object){
        $keyword = trim($object->Content);
        $content = "";
        if($keyword == "功能"){
            $content = "回复以下关键词，查询对应内容\n\n个人信息\n学分\n课表\n考勤\n考试时间\n成绩\n个人奖惩\n开设课程";
        } else if ($keyword == "个人信息" || $keyword == "学分" || $keyword == "课表" || $keyword == "考勤"
            || $keyword == "考试时间" || $keyword == "成绩" || $keyword == "个人奖惩" || $keyword == "开设课程"
            || $keyword == "违规"){
            if($this->checkUser($object) == 0){
                $this->insertUser($object);
                if ($this->checkUserMessage($object)){
                    $content = "你尚未绑定\n\n请输入\nSCSE+学号+密码\n如：SCSE1540129144password\n进行绑定";
                }
            } else if ($this->checkUserMessage($object)){
                $content = "你尚未绑定\n\n请输入\nSCSE+学号+密码\n如：SCSE1540129144password\n进行绑定";
            } else {
                $account = $this->getAccount($object);
                $username = $account[0];
                $password = $account[1];
                $content = $this->checkAccount($username,$password);
                while($content == "myscse错误"){
                    $content = $this->checkAccount($username,$password);
                }
                if($content == "帐号或密码错误"){
                    $content = "帐号或密码错误";
                } else {
                    $content = $this->getMessage($keyword,$content);
                }
            }
        } else if (substr($keyword,0,4) == "SCSE"){
            if($this->checkUser($object) == 0) {
                $this->insertUser($object);
            }
            if($this->bindingMessage($object,$keyword)){
                $account = $this->getAccount($object);
                $username = $account[0];
                $password = $account[1];
                $content = $this->checkAccount($username,$password);
                while($content == "myscse错误"){
                    $content = $this->checkAccount($username,$password);
                }
                if($content == "帐号或密码错误"){
                    $content = "帐号或密码错误";
                } else {
                    $content = "绑定成功";
                }
            } else {
                $content = "绑定失败";
            }
        } else {
            $content = "暂无此关键词";
        }
        $result = $this->transmitText($object,$content);
        return $result;
    }

    //回复文本消息
    private function transmitText($object, $content){
        if (!isset($content) || empty($content)){
            return "";
        }

        $xmlTpl = "<xml>
                   <ToUserName><![CDATA[%s]]></ToUserName>
                   <FromUserName><![CDATA[%s]]></FromUserName>
                   <CreateTime>%s</CreateTime>
                   <MsgType><![CDATA[text]]></MsgType>
                   <Content><![CDATA[%s]]></Content>
                   </xml>";
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), $content);

        return $result;
    }

    //连接数据库
    private function connection(){
        //$db = new PDO("mysql:host=w.rdc.sae.sina.com.cn;dbname=app_yhw13632212554;port=3306", "0nmkj52mkn","13l2x50wwyx3ii2k5xyh2k25m3j41xkzi4j3x11l");
        $db = new PDO("mysql:host=localhost;port=3306;dbname=yhw","root","root");
        $db->query('set names utf8');
        return $db;
    }

    //测试是否存在用户
    private function checkUser($object){
        $db = $this->connection();
        $WeChat = $object->FromUserName;
        $result = $db->query("select wechat from user where wechat='$WeChat'");
        $row = $result->rowCount();
        $db = null;
        return $row;
    }

    //插入用户
    private function insertUser($object){
        $content = "";
        $db = $this->connection();
        $sql = "insert into user(id,wechat,account,password) VALUES (NULL,:wechat,NULL,NULL)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':wechat',$object->FromUserName);
        $stmt->execute();
        $result = $this->transmitText($object, $content);
        $db = null;
        return $result;
    }

    //验证是否绑定学号、密码
    private function checkUserMessage($object){
        $db = $this->connection();
        $WeChat = $object->FromUserName;
        $result = $db->query("select account,password from user where wechat='$WeChat'");
        while($row=$result->fetch(1)) {
            if($row["account"] == null && $row["password"] == null){
                return true;
            }
        }
        $db = null;
        return false;
    }

    //绑定学号、密码
    private function bindingMessage($object,$text){
        $account = substr($text,4,10);
        $password = substr($text,14);
        if($account == "" || $password == ""){
            return false;
        }
        $db = $this->connection();
        $WeChat = $object->FromUserName;
        $sql = "update user set account=:account,password=:password where wechat=:wechat";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':account',$account);
        $stmt->bindParam(':password',$password);
        $stmt->bindParam(':wechat',$WeChat);
        $stmt->execute();
        if($this->checkUserMessage($object)) {
            $db = null;
            return false;
        }
        $db = null;
        return true;
    }

    //获取学号、密码
    private function getAccount($object){
        $db = $this->connection();
        $WeChat = $object->FromUserName;
        $result = $db->query("select account,password from user where wechat='$WeChat'");
        while($row=$result->fetch(1)){
            $username = $row['account'];
            $password = $row['password'];
            if($username != null && $password != null){
                $db = null;
                return $row;
            }
        }
        $db = null;
        return false;
    }

    //模拟登录
    private function checkAccount($username,$password){
        $url = "http://class.sise.com.cn:7001/sise/login.jsp";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        $data = curl_exec($curl);
        curl_close($curl);
        $data = iconv("gbk","utf-8",$data);

        $pattern1 = '/<input type="hidden" name="(.*)"/';
        preg_match_all($pattern1, $data, $matches1);
        $res1[0] = $matches1[1][0];

        preg_match('/Set-Cookie:(.*);/iU',$data,$str); //正则匹配
        $JSESSIONID = $str[1]; //获得COOKIE（SESSIONID）
        $JSESSIONID = substr($JSESSIONID,12);
        $JSESSIONID = substr($JSESSIONID,0,stripos($JSESSIONID,"!"));

        preg_match_all('/<input id="random"   type="hidden"  value="(.*)"/', $data, $matches);
        $random = substr($matches[1][0],0,13); //获得random
        $md5 = strtoupper(md5("http://class.sise.com.cn:7001/sise/login.jsp".$JSESSIONID.$random));

        $url_name = substr($res1[0],0,32);
        $url_value = substr($res1[0],42);
        $token = $this->patch($md5,$random);

        $cookie = tempnam('./temp','cookie');
        $post = "$url_name=$url_value&random=$random&token=$token&username=$username&password=$password";
        $url = "http://class.sise.com.cn:7001/sise/login_check_login.jsp";
        $result = $this->curl_request($url,$post,$cookie);

        if(stripos($result,"你输入的密码错误")){
            $content = "帐号或密码错误";
            return $content;
        } else if (stripos($result,"警告")){
            $content = "myscse错误";
            return $content;
        } else {
            return $cookie;
        }
    }

    //获取信息
    private function getMessage($keyword,$cookie){
        $url = "http://class.sise.com.cn:7001/sise/module/student_states/student_select_class/main.jsp";
        $all = $this->curl_request($url,false,false,$cookie);
        preg_match('/course\/courseViewAction.do\?method=doMain&studentid=([\S]*?)\'/',$all,$arr);
        $studentID = $arr[1];

        if($keyword=="个人信息"){
            $url = "http://class.sise.com.cn:7001/SISEWeb/pub/course/courseViewAction.do?method=doMain&studentid=$studentID";
            $pm = $this->curl_request($url,false,false,$cookie);
            $pm = substr($pm,stripos($pm,'<table width="90%" border="0" class="table1" cellspacing="1" align="center" cellpadding="0">'));
            $pm = substr($pm,stripos($pm,'<table width="100%" border="0" cellspacing="2" cellpadding="0" align="left">'));
            $pm = substr($pm,0,stripos($pm,'</table>'));
            $arr = $this->setArray($pm,8,3);
            $content = $this->getContent($arr);
            $content = "个人信息：".$content;
            $content = str_replace("&nbsp;","",$content);
            return $content;
        }
        else if ($keyword=="学分"){
            $url = "http://class.sise.com.cn:7001/SISEWeb/pub/course/courseViewAction.do?method=doMain&studentid=$studentID";
            $credit = $this->curl_request($url,false,false,$cookie);
            $credit = substr($credit,stripos($credit,'<HR noShade SIZE=1>'));
            $credit = substr($credit,stripos(substr($credit,strlen("<HR noShade SIZE=1>")),'<HR noShade SIZE=1>'));
            $arr = $this->setArray($credit,6,4);
            $content = $this->getContent($arr);
            $content = "学分：".$content;
            $content = str_replace("&nbsp;","",$content);
            return $content;
        }
        else if ($keyword=="课表"){
            $url = "http://class.sise.com.cn:7001/sise/module/student_schedular/student_schedular.jsp";
            $class = $this->curl_request($url,false,false,$cookie);
            $week = substr($class,stripos($class,"教学周")+14,2);
            if(date("w")!=0){
                $day_num=date("w");
            } else {
                $day_num=7;
            }
            $week_array = array("日","一","二","三","四","五","六");
            $day = $week_array[date("w")];
            $class = substr($class,stripos($class,'<table borderColor="#999999" cellSpacing="0" bordercolordark="#ffffff" cellPadding="0" width="95%" border="1" align="center">'));
            $arr = $this->setArray($class,8,8);
            $content = "本日课程：\n"."星期".$day."\n";
            for($i=0;$i<8;$i++){
                for($j=0;$j<8;$j++){
                    if(stripos($arr[$i][$j],",")){
                        $arr[$i][$j] = explode(",",$arr[$i][$j]);
                    }
                }
                if($day=="五"){
                    $arr[4][0] = "7 - 8 节13:20 - 14:40";
                    $arr[5][0] = "9 - 10 节14:50 - 16:10";
                }
                if(sizeof($arr[$i][$day_num])!=1){
                    for($a=0;$a<sizeof($arr[$i][$day_num]);$a++){
                        if(stripos($arr[$i][$day_num][$a],$week)){
                            $content = $content.$arr[$i][0]."：\n";
                            $class_name = substr($arr[$i][$day_num][$a],0,stripos($arr[$i][$day_num][$a],"("));
                            $class_room = substr($arr[$i][$day_num][$a],stripos($arr[$i][$day_num][$a],"["),stripos($arr[$i][$day_num][$a],"]")-stripos($arr[$i][$day_num][$a],")"));
                            $content = $content.$class_name.$class_room."\n";
                        }
                    }
                } else {
                    if(stripos($arr[$i][$day_num],$week)){
                        $content = $content.$arr[$i][0]."：\n";
                        $class_name = substr($arr[$i][$day_num],0,stripos($arr[$i][$day_num],"("));
                        $class_room = substr($arr[$i][$day_num],stripos($arr[$i][$day_num],"["),stripos($arr[$i][$day_num],"]")-stripos($arr[$i][$day_num],")"));
                        $content = $content.$class_name.$class_room."\n";
                    }
                }
            }
            return $content;
        }
        else if ($keyword=="考勤"){
            preg_match('/attendance\/studentAttendanceViewAction.do\?method=doMain&studentID='.$studentID.'&gzcode=([\S]*?)\'/',$all,$arr);
            $url = "http://class.sise.com.cn:7001/SISEWeb/pub/studentstatus/attendance/studentAttendanceViewAction.do?method=doMain&studentID=$studentID&gzcode=$arr[1]";
            $attendance = $this->curl_request($url,false,false,$cookie);
            $attendance = substr($attendance,stripos($attendance,'<table width="99%" class="table" cellspacing="0" id="table1">'));
            $attendance = substr($attendance,0,stripos($attendance,'</table>'));
            $attendance = str_replace("th","td",$attendance);
            $arr = $this->setArray($attendance,3,substr_count($attendance,"tr")/2);
            $content = "考勤：\n";
            for($i=1;$i<sizeof($arr);$i++){
                $content = $content."(".$i.")\n";
                for($j=0;$j<3;$j++){
                    if($arr[$i][$j]==null){
                        $content = $content.$arr[0][$j].":全勤\n";
                    } else {
                        $content = $content.$arr[0][$j].":".$arr[$i][$j]."\n";
                    }
                }
            }
            $content = str_replace("&nbsp;","",$content);
            return $content;
        }
        else if ($keyword=="考试时间"){
            preg_match('/exam\/studentexamAction.do\?method=doMain&studentid=([\S]*?)\'/',$all,$arr);
            $url = "http://class.sise.com.cn:7001/SISEWeb/pub/exam/studentexamAction.do?method=doMain&studentid=$arr[1]";
            $time = $this->curl_request($url,false,false,$cookie);
            $time = substr($time,stripos($time,'<table width="90%" class="table" cellspacing="1">'));
            $time = str_replace("th","td",$time);
            $arr = $this->setArray($time,8,substr_count($time,"tr")/2);
            $content = "考试时间：\n";
            for($i=1;$i<sizeof($arr);$i++){
                $content = $content."(".$i.")";
                for($j=0;$j<7;$j++){
                    $content = $content.$arr[0][$j].":".$arr[$i][$j]."\n";
                }
                $content = $content."\n";
            }
            return $content;
        }
        else if ($keyword=="成绩"){
            $url = "http://class.sise.com.cn:7001/sise/module/student_schedular/student_schedular.jsp";
            $semester = $this->curl_request($url,false,false,$cookie);
            $semester = substr($semester,stripos($semester,'<span class="style17">')+strlen('<span class="style17">'));
            $semester = substr($semester,0,stripos($semester,'</span>'));
            $semester = str_replace("\t","",$semester);
            $semester = str_replace("\r\n","",$semester);
            $semester = substr($semester,0,5).substr($semester,13,16);
            $semester = str_replace(" ","",$semester);

            $url = "http://class.sise.com.cn:7001/SISEWeb/pub/course/courseViewAction.do?method=doMain&studentid=$studentID";
            $grade = $this->curl_request($url,false,false,$cookie);
            $grade_required = substr($grade,stripos($grade,'<table width="90%" class="table" align="center">'));
            $grade_required = substr($grade_required,0,stripos($grade_required,'</table>'));
            $grade_required = str_replace("th","td",$grade_required);
            $grade_required_arr = $this->setArray($grade_required,10,substr_count($grade_required,"tr")/2);

            $grade_elective = substr($grade,stripos($grade,'<table width="90%" class="table" align="center">')+strlen('<table width="90%" class="table" align="center">'));
            $grade_elective = substr($grade_elective,stripos($grade_elective,'<table width="90%" class="table" align="center">'));
            $grade_elective = substr($grade_elective,0,stripos($grade_elective,'</table>'));
            $grade_elective = str_replace("th","td",$grade_elective);
            $grade_elective_arr = $this->setArray($grade_elective,9,substr_count($grade_elective,"tr")/2);

            $content = $semester."成绩:\n\n必修课:\n";
            for($i=0;$i<sizeof($grade_required_arr);$i++){
                if($grade_required_arr[$i][7]==$semester){
                    $content = $content.$grade_required_arr[$i][2].":".$grade_required_arr[$i][8]."\n";
                }
            }
            $content = $content."\n选修课:\n";
            for($i=0;$i<sizeof($grade_elective_arr);$i++){
                if($grade_elective_arr[$i][6]==$semester){
                    $content = $content.$grade_elective_arr[$i][1].":".$grade_elective_arr[$i][7]."\n";
                }
            }
            return $content;
        }
        else if ($keyword=="个人奖惩"){
            preg_match('/encourage_punish\/encourage_punish.jsp\?stuname=([\S]*?)&gzcode=([\S]*?)&serialabc=([\S]*?)\'/',$all,$arr);
            $stuname=$arr[1];
            $gzcode=$arr[2];
            $serialabc=$arr[3];
            $url="http://class.sise.com.cn:7001/sise/module/encourage_punish/encourage_punish.jsp?stuname=$stuname&gzcode=$gzcode&serialabc=$serialabc";
            $encourage_punishment=$this->curl_request($url,false,false,$cookie);
            $encourage=substr($encourage_punishment, stripos($encourage_punishment,'<strong>奖励信息</strong></td></tr>')+strlen('<strong>奖励信息</strong></td></tr>'));
            $encourage=substr($encourage,0,stripos($encourage,'</table>'));
            $encourage_arr=$this->setArray($encourage,6,substr_count($encourage,"/tr"));
            $punishment=substr($encourage_punishment,stripos($encourage_punishment,'<strong>惩处信息</strong></td></tr>')+strlen('<strong>惩处信息</strong></td></tr>'));
            $punishment=substr($punishment,0,stripos($punishment,'</table>'));
            $punishment_arr=$this->setArray($punishment,8,substr_count($punishment,"/tr"));

            $en_content="奖励：\n";
            if(sizeof($encourage_arr)==1){
                $en_content=$en_content."(无)\n";
            }
            else{
                for($i=1;$i<sizeof($encourage_arr);$i++){
                    $en_content=$en_content."(".$i.")\n";
                    for($j=0;$j<6;$j++){
                        $en_content=$en_content.$encourage_arr[0][$j].":".$encourage_arr[$i][$j]."\n";
                    }
                }
            }
            $pu_content="\n惩处：\n";
            if(count($punishment_arr)==1){
                $pu_content=$pu_content."(无)\n";
            }
            else{
                for($i=1;$i<sizeof($punishment_arr);$i++){
                    $pu_content=$pu_content."(".$i.")\n";
                    for($j=0;$j<6;$j++){
                        $pu_content=$pu_content.$punishment_arr[0][$j].":".$punishment_arr[$i][$j]."\n";
                    }
                }
            }
            return $en_content.$pu_content;
        }
        else if ($keyword=="开设课程"){
            $url="http://class.sise.com.cn:7001/sise/module/selectclassview/selectclasscourse_view.jsp";
            $selectcourse=$this->curl_request($url,false,false,$cookie);
            $requiredcourse=substr($selectcourse,stripos($selectcourse,'<td colspan="9" class="tablehead style1  style3"> 必 修 课</td></tr>')+strlen('<td colspan="9" class="tablehead style1  style3"> 必 修 课</td></tr>'));
            $requiredcourse=substr($requiredcourse,0,stripos($requiredcourse,'</table>'));
            $required_arr=$this->setArray($requiredcourse,7,substr_count($requiredcourse,"/tr"));

            $req_content="下学期必修课：\n";
            for($i=1;$i<sizeof($required_arr);$i++){
                $req_content=$req_content."(".$i.")\n";
                for($j=0;$j<6;$j++){
                    $req_content=$req_content.$required_arr[0][$j].":".$required_arr[$i][$j]."\n";
                }
            }
            $req_content = str_replace("&nbsp;","",$req_content);
            return $req_content;
        }
        else if ($keyword=="违规"){
            preg_match('/studentstatus\/lateStudentAction.do\?method=doMain&gzCode=([\S]*?)&md5Code=([\S]*?)\'/',$all,$arr);
            $gzcode=$arr[1];
            $md5Code=$arr[2];
            $url="http://class.sise.com.cn:7001/SISEWeb/pub/studentstatus/lateStudentAction.do?method=doMain&gzCode=$gzcode&md5Code=$md5Code";
            $lateStudentAction=curl_request($url,false,false,$cookie);

            $obeyaction=substr($lateStudentAction,stripos($lateStudentAction,'<caption><b>违规用电记录</b></caption>'));
            $obeyaction=substr($obeyaction,0,stripos($obeyaction,'</table>'));
            $obeyaction=str_replace("th","td",$obeyaction);
            $obey_arr=setArray($obeyaction,8,substr_count($obeyaction,"/tr"));
            $obey_content="违规用电记录：\n";
            for($i=1;$i<sizeof($obey_arr);$i++){
                // $obey_content=$obey_content."(".$i.")\n";
                for($j=0;$j<8;$j++){
                    $obey_content=$obey_content.$obey_arr[0][$j].":".$obey_arr[$i][$j]."\n";
                }
            }
            return $obey_content;
        }
        else {
            return "错误类型";
        }
    }

    private function curl_request($url,$post='',$cookie='', $returnCookie=''){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        }
        if($cookie) {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie);
        }
        if($returnCookie) {
            curl_setopt($curl,CURLOPT_COOKIEFILE, $returnCookie);
        }
        $data = curl_exec($curl);
        curl_close($curl);
        $data = iconv("gbk","utf-8//IGNORE",$data);
        return $data;
    }

    private function setArray($text,$x,$y){
        $text = preg_replace("/<([a-zA-Z]+)[^>]*>/","<\\1>",$text);
        $td_p = "/<td>(.*)<\/td>/iUs";
        preg_match_all($td_p,$text,$td);

        $i=0;
        $arr=Array();
        for ($j=0;$j<$y;$j++){
            for($a=0;$a<$x;$a++){
                $td[1][$i] = strip_tags($td[1][$i]);
                $td[1][$i] = trim($td[1][$i]);
                $td[1][$i] = str_replace("\t","",$td[1][$i]);
                $td[1][$i] = str_replace("\r\n","",$td[1][$i]);
                $td[1][$i] = str_replace("\r","",$td[1][$i]);
                $td[1][$i] = str_replace("\n","",$td[1][$i]);
                $arr[$j][$a] = $td[1][$i];
                if($i<sizeof($td[1])-1) $i++;
            }
        }
        return $arr;
    }

    private function getContent($arr){
        $content = "";
        for($i=0;$i<sizeof($arr);$i++){
            for($j=0;$j<sizeof($arr[$i]);$j++){
                if($j%2==0)
                    $content = $content."\n";
                $content = $content.$arr[$i][$j];
            }
        }
        return $content;
    }

    private function patch($f,$e){
        $a = [];
        for ($c = 0; $c < strlen($e); $c++) {
            array_push($a, $f[$c]);
            array_push($a, $e[$c]);
        }
        for ($b = strlen($e); $b < strlen($f); $b++) {
            array_push($a, $f[$b]);
        }
        return implode("", $a);
    }
}
?>