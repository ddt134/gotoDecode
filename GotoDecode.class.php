<?php
class GotoDecode{
    private static $error;
    private static $msg;
    private static $content;
    //private static $classCount;
    public static function decode($file){

    }
    private static function splitClass(){
        self::$content=preg_replace('/\s*<\?php\s*/is','',self::$content);
        $className='[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
        preg_match_all('/\s*((?:abstract\s*|final\s*)?(?:interface|trait|class)\s*'.$className.'\s*(?:extends\s*'.$className.')?(?:implements\s*'.$className.')?)\s*{/is',self::$content,$matches,PREG_SET_ORDER);
        //self::$classCount=count($matches);
    }

    private static function splitFunc($classContent){

    }

    private static function getContentFromBrace($content){

    }


    private static function loadFile($file){
        if(!file_exists($file)){
            return self::error('文件不存在!');
        }
        self::$content=file_get_contents($file);
        return self::$content;
    }

    private static function error($msg){
        self::$error=1;
        self::$msg=$msg;
        return false;
    }
}

//没有多个<?php
//没有注释(解码出来后不会保留注释,注释如果带有一些匹配的关键字会出错)
//没有大括号不对称的语法错误
echo '\xab';
echo "\xab";