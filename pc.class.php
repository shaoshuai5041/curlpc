<?php
//phpQuery匹配类库
require './phpQuery/phpQuery.php';
//引入配置文件
require './config.php';
class Pc{
  //请求方法
  public function request($url,$https=true,$method='get',$data=null){
    //1.初始化
    $ch = curl_init($url);
    //2.设置curl
    //返回数据不输出
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //根据url设置referer
    $host = parse_url($url);
    $host = $host['host'];
    curl_setopt($ch, CURLOPT_REFERER, 'http://'.$host);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36');
    //满足https
    if($https === true){
      //绕过ssl验证
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    //满足post
    if($method === 'post'){
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    //3.发送请求
    $content = curl_exec($ch);
    //4.关闭资源
    curl_close($ch);
    return $content;
  }
  //写入数据库方法
  public function add($data,$mysqli){
    $keys = implode(array_keys($data), ',');
    $keys = 'id,'.$keys;
    $values = implode(array_values($data), '\',\'');
    $values .= '\'';
    $values = 'null,\''.$values;
    $sql = 'INSERT INTO curl.zhilian ('.$keys.') VALUES ('.$values.');';
    return $mysqli->multi_query($sql);
    // echo mysqli_error($mysqli);
  }
  //通过招聘的列表页获取所有单页链接
  public function getIndex($area='北京',$keyword='php',$page=1){
    //定义一个篮子,用来存储所有的招聘链接
    $hrefsArray = array();
    //遍历,确定取几页
    for ($i=1; $i <= $page; $i++) {
      // $url = 'http://sou.zhaopin.com/jobs/searchresult.ashx?kw='.$keyword.'&p='.$i;
      $url = 'http://sou.zhaopin.com/jobs/searchresult.ashx?jl='.$area.'&kw='.$keyword.'&sm=0&p='.$i;
      $content = $this->request($url,false);
      // var_dump($content);die();
      $doc = phpQuery::newDocumentHTML($content);
      // phpQuery::selectDocument($doc);
      $hrefs = array();
      foreach (pq('a', $doc) as $one) {
        $href = $one->getAttribute('href');
        //过滤出具体招聘链接
        if(strpos($href, 'http://jobs.zhaopin.com/') !== false && strpos($href, '.htm') !== false){
          $hrefs[] = $href;
        }
      }
      array_push($hrefsArray, $hrefs);
    }
    return $hrefsArray;
  }
  //访问并获取每一页的招聘信息
  public function getInfo(){
    $mysqli = new mysqli(HOST, USER, PASSWORD, DATABASE);
    //读取文件，或者直接调用抓取所有的招聘信息链接
    $hrefsArray = $this->getIndex();
    $InfoArray = array();
    foreach ($hrefsArray as $key => $value) {
      $pageInfo = array();
      foreach ($value as $k => $v) {
        //访问获取每一页的具体信息
        $content = $this->request($v,false);
        $doc = phpQuery::newDocumentHTML($content);
        $obj = pq($doc);
        $title = $obj->find('h1:eq(0)')->text();
        $companyName = $obj->find('h2:eq(0)')->text();
        //薪资
        $salary = $obj->find('.terminal-ul li:eq(0)')->text();
        //工作地点
        $location = $obj->find('.terminal-ul li:eq(1)')->text();
        //发布时间
        $time = $obj->find('.terminal-ul li:eq(2)')->text();
        //工作性质
        $jobType = $obj->find('.terminal-ul li:eq(3)')->text();
        //工作经验
        $experience = $obj->find('.terminal-ul li:eq(4)')->text();
        //最低学历
        $education = $obj->find('.terminal-ul li:eq(5)')->text();
        //招聘人数
        $nums = $obj->find('.terminal-ul li:eq(6)')->text();
        //职位类别
        $jobCategory = $obj->find('.terminal-ul li:eq(7)')->text();
        //招聘信息
        $jobInfo = $obj->find('.tab-inner-cont:eq(0)')->html();
        $jobInfo = pq($jobInfo)->not('b,h2')->html();
        $jobInfo = pq($jobInfo)->not('button')->html();
        //工作地址
        $address = $obj->find('h2:eq(1)')->text();
        $oneInfo = array(
            'title' => $title,
            'companyName' => $companyName,
            'salary' => $salary,
            'location' => $location,
            'time' => $time,
            'jobType' => $jobType,
            'experience' => $experience,
            'education' => $education,
            'nums' => $nums,
            'jobCategory' => $jobCategory,
            'jobInfo' => addslashes($jobInfo),
            'address' => $address,
            'url' => '<a href="'.$v.'" target="_blank"/>点击跳转到网页</a>',
        );
        $pageInfo[] = $oneInfo;
        //判断当前进度的index
        if($key == 0){
           $index = $k+1;
        }else{
           $index = $key*60+($k+1);
        }
        //计算总数量
        $count = count($hrefsArray)*count($value);
        $progress = round(($index/$count)*100);
        echo "<script type=\"text/javascript\">$('.progress-bar').css('width','$progress%');</script>";
        ob_flush();
        flush();
      }
      $InfoArray[] = $pageInfo;
    }
    $resultLength = file_put_contents('./info.json', json_encode($InfoArray));
    if($resultLength > 0){
      echo "<script type=\"text/javascript\">self.location=\"http://localhost/curlpc/show.php\"</script>";
      exit();
    }
  }
}