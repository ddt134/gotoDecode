<?php

class GotoDecode
{
    private $fileName;
    public static function decode($file)
    {
        try {
            $obj = new self();
            $fileContent = $obj->loadFile($file);
            $fileContent = $obj->trimComments($fileContent);
            $classArr = $obj->splitClass($fileContent);
            $funcArr = $obj->splitFunc($classArr);
            return $obj->saveAsFile($funcArr);
        } catch (Exception $e) {
            var_dump($e);
            return true;
        }

        //var_dump($funcArr);
        //exit;
        //$this->splitFunc()
    }

    private function splitClass($fileContent)
    {
        $fileContent = preg_replace('/^\s*<\?php\s*|\?>\s*/is', '', $fileContent);
        $className = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
        //preg_match_all('/\s*((?:abstract\s*|final\s*)?(?:interface|trait|class)\s*'.$className.'\s*(?:extends\s*'.$className.')?(?:implements\s*'.$className.')?)\s*{/is',$this->$content,$matches,PREG_SET_ORDER);
        $arrX = preg_split('/\s*((?:abstract\s*|final\s*)?(?:interface|trait|class)\s*' . $className . '\s*(?:extends\s*' . $className . ')?(?:implements\s*' . $className . ')?)\s*(?={)/is', $fileContent, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);//
        if (!$arrX) {
            throw new Exception('划分类的时候出错');
        }
        $arrY[]=$arrX[0];
        $countX=count($arrX);
        if($countX>1){
            for($i=1;$i<$countX;$i++){
                if(($braceIndex=strrpos($arrX[$i],'}'))!==false){
                    $braceIndex=intval($braceIndex);
                    $headString=substr($arrX[$i],0,$braceIndex+1);
                    $tailString=trim(substr($arrX[$i],$braceIndex+1));
                    $arrY[]=$headString;
                    if(!empty($tailString)){
                        $arrY[]=$tailString;
                    }
                }else{
                    $arrY[]=$arrX[$i];
                }
            }
        }
        //var_dump($arrY);exit;

        $count =count($arrY) ;
        $codeStack = [];
        /*
         * 根据这里特殊的数据格式来划分,普通代码,类名,类的{}部分
         * (1.类名不带';'2.类的{}部分紧跟在类名之后)
         * [
         *  '普通代码;',
         *  '类名',
         *  '类的{}部分',
         *  '类名',
         *  '类的{}部分',
         *  ...
         * ]
         * */
        //var_dump($arrY);exit;
        for ($i = 0; $i < $count; $i++) {
            if (strpos($arrY[$i], ';') === false) {
                $arr = ['className' => $arrY[$i]];
                $i++;
                $temp = preg_split('/^\s*({.*})(?!\S)/s', $arrY[$i], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $arr['classContent'] = $temp[0];
                $codeStack[] = $arr;
                if (count($temp)>1&&!empty(trim($temp[1]))) {
                    $codeStack[] = $this->transGoto($temp[1]);
                }
            } else {
                $codeStack[] = $this->transGoto($arrY[$i]);
            }
        }
        return $codeStack;
    }

    private function splitFunc($classArr)
    {
        //var_dump($classArr);exit;
        foreach($classArr as $k=>$v){
            if(is_array($v)){
                $string=preg_replace('/^\s*{|}\s*$/','',$v['classContent']);
                $funcName = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
                $res = preg_split('/\s*((?:public\s*|private\s*|protected\s*|final\s*|abstract\s*)?(?:static\s*)?function\s*' . $funcName . '\s*\(.*?\))\s*(?={)/is',$string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $classArr[$k]['classContent']=[];
                if(!empty($res)){
                    $count = count($res);
                    for ($i = 0; $i < $count; $i++) {
                        if (strpos($res[$i], ';') === false) {
                            $arr = ['funcName' => $res[$i]];
                            $i++;
                            $temp = preg_split('/^\s*({.*})(?!\S)/s', $res[$i], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                            $funcContent=trim(preg_replace('/^\s*{|}\s*$/s','',$temp[0]));
                            $arr['funcContent'] =empty($funcContent)?'{}':'{'.$this->transGoto($funcContent).'}';
                            $classArr[$k]['classContent'][] = $arr;
                            if (count($temp)>1&&!empty(trim($temp[1]))) {
                                $classArr[$k]['classContent'][] = $this->transGoto($temp[1]);
                            }
                        } else {
                            $classArr[$k]['classContent'][] = $this->transGoto($res[$i]);
                        }
                    }
                }


            }

        }
        return $classArr;
    }

    private function transGoto($string)
    {
        $string=preg_replace('/^\s*$/s', '', $string);
        $string=preg_replace('/([{}])(?=\s*goto)/si','\1;',$string);
        $arr = explode(';',$string);
        array_pop($arr);//删除最后的空行
        //$count=count($arr);
        //var_dump($arr);exit;
        $res = [];
        $i = 0;
        $recallStack=[];
        //while这一块不整个使用递归实现是为了防止超出函数的调用层数限制
        while (1) {
            //碰到{设置回溯点
            if(!empty($res)&&preg_match('/{\s*$/s', $res[count($res)-1])){
                $recallStack[]=$this->findBackBraceKey($arr,$i);
            }
            if (!preg_match('/^\s*goto\s*(\w*)\s*$/is', $arr[$i], $matches)) {
                $res[] = trim($arr[$i]);
                $i++;
                if($i>=count($arr)){
                    break;
                }
            } else {
                $code=$this->findRealCode($arr,$matches[1]);
                if(!$code){
                    if(empty($recallStack)){
                        break;
                    }else{
                        $i=array_pop($recallStack);
                        continue;
                    }
                }
                $res[]=$code['value'];
                $i=intval($code['key'])+1;
            }
        }
        $res=implode(';' . PHP_EOL, $res).';';
        $res=preg_replace('/([{}])\s*;/s','\1',$res);
        return $res;

    }

    public function test()
    {
        $res = $this->transGoto(' goto W25NC; C2Ym6: $b = substr($color, 2, 1) . substr($color, 2, 1); goto QCe2s; W25NC: $color = str_replace(\'#\', \'\', $color); goto liq2p; xrU7g: goto qsh5h; goto hc2Vj; QDzHf: $title_bg = hexdec(substr($color, 0, 2)) . \',\' . hexdec(substr($color, 2, 2)) . \',\' . hexdec(substr($color, 4, 2)); goto g0TvJ; mhcZW: $color = $color; goto T22nz; hc2Vj: YbVdm: goto QDzHf; T22nz: $r = substr($color, 0, 1) . substr($color, 0, 1); goto ZKFCz; QCe2s: $title_bg = hexdec($r) . \',\' . hexdec($g) . \',\' . hexdec($b); goto xrU7g; liq2p: if (strlen($color) > 3) { goto YbVdm; } goto mhcZW; g0TvJ: qsh5h: goto Bt1JK; Bt1JK: return $title_bg; goto Nc79t; ZKFCz: $g = substr($color, 1, 1) . substr($color, 1, 1); goto C2Ym6; Nc79t: ');
        var_dump($res);
    }

    private function findRealCode($arr,$target){
        foreach ($arr as $k => $v) {
            if (preg_match('/\s*' . $target . '\s*:/s', $v)) {
                //可能会误删case:或者字符串中带xxxx:的部分
                $code=preg_replace('/\s*\w*\s*:/s','',$v);
                if(preg_match('/^\s*goto\s*(\w*)\s*$/is', $code, $matches)){
                    return $this->findRealCode($arr,trim($matches[1]));
                }else{
                    return ['key'=>$k,'value'=>$code];
                }
            }
        }
        return false;
    }

    private function findBackBraceKey($arr,$start){
        $count=count($arr);
        for($i=intval($start)+1;$i<$count;$i++){
            if(trim($arr[$i])=='}'){
                return $i;
            }
        }
        throw new Exception("没有找到{$arr[$start]}的回括号");
    }

    private function getCodeBlock($content)
    {
        $res = ['inBlock' => [], 'notInBlock' => []];
        $stack = [];

    }

    private function trimComments($fileContent)
    {
        //删除注释
        //删除换行
        $fileContent=preg_replace('/\r\n|\r/','',$fileContent);
        return $fileContent;
    }

    private function saveAsFile($data){
        //var_dump($data);exit;
        $content='<?php'.PHP_EOL;
        foreach($data as $k=>$v){
            if(is_array($v)){
                $content.="{$v['className']} {".PHP_EOL;
                foreach($v['classContent'] as $key=>$value){
                    if(is_array($value)){
                        $content.=$value['funcName'].$value['funcContent'].PHP_EOL;
                    }else{
                        $content.=$value.PHP_EOL;
                    }
                }
                $content.=PHP_EOL.'}'.PHP_EOL;
            }else{
                $content.=$v.PHP_EOL;
            }

        }
        if(!is_writable(__DIR__)){
            throw new Exception(__DIR__.'没有写权限!');
        }
        file_put_contents(__DIR__.'/new'.ucfirst($this->fileName),$content);
        return true;
    }

    private function loadFile($file)
    {
        if (!file_exists($file)) {
            throw new Exception('文件不存在');
        }
        $this->fileName=basename($file);
        return file_get_contents($file);
    }

    /*private static function error($msg){
        $this->$error=1;
        $this->$msg=$msg;
        return false;
    }*/
}

//没有多个<?php
//没有注释(解码出来后不会保留注释,注释如果带有一些匹配的关键字会出错)
//字符串中不能带有关键字
//没有大括号不对称的语法错误
//如果一个文件内同时存在定义类和调用类的代码,假设调用的类的代码都在文件底部
GotoDecode::decode('./test/site.php');//'D:\wamp\www\sjyn\addons\yyf_company\wxapp.php'
//$test = new GotoDecode();
//$test->test();



