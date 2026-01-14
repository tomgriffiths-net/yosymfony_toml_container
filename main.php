<?php

use Yosymfony\Toml\Toml;

class yosymfony_toml_container{
    //public static function command($line):void{}
    public static function init():void{
        require_once 'packages/yosymfony_toml_container/files/vendor/autoload.php';
    }

    /**
     * Parses TOML into a PHP array.
     *
     * Usage:
     * <code>
     *  $array = yosymfony_toml_container::parse('key = "[1,2,3]"');
     *  print_r($array);
     * </code>
     *
     * @param string $input A string containing TOML
     * @param bool $resultAsObject (optional) Returns the result as an object
     *
     * @return mixed The TOML converted to a PHP value
     */
    public static function parse(string $input, bool $resultAsObject=false):mixed{
        try{
            return \Yosymfony\Toml\Toml::parse($input, $resultAsObject);
        }
        catch(\Yosymfony\Toml\Exception\ParseException $e){
            return false;
        }
    }

    /**
     * Parses a TOML file into a PHP array.
     *
     * Usage:
     * <code>
     *  $array = yosymfony_toml_container::parseFile('config.toml');
     *  print_r($array);
     * </code>
     *
     * @param string $input A string containing TOML
     * @param bool $resultAsObject (optional) Returns the result as an object
     *
     * @return mixed The TOML converted to a PHP value
     */
    public static function parseFile(string $filename, bool $resultAsObject=false):mixed{
        try{
            return \Yosymfony\Toml\Toml::parseFile($filename, $resultAsObject);
        }
        catch(\Yosymfony\Toml\Exception\ParseException $e){
            return false;
        }

        return $data;
    }


    //Toms extras (helped by chatgpt)

    public static function arrayToToml(array $data):string{
        $tb = new \Yosymfony\Toml\TomlBuilder(2);
        self::processTable($tb, $data, null);
        return $tb->getTomlString();
    }

    private static function processTable(\Yosymfony\Toml\TomlBuilder $tb, array $data, ?string $path):void{
        if($path !== null){
            $tb->addTable($path);
        }

        foreach($data as $key => $value){
            if(is_array($value)){
                if(self::isAssoc($value)){
                    // Nested table
                    self::processTable(
                        $tb,
                        $value,
                        $path === null ? $key : "$path.$key"
                    );
                }
                else{
                    // Indexed array
                    if(self::isArrayOfTables($value)){
                        foreach($value as $row){
                            $tb->addArrayOfTable(
                                $path === null ? $key : "$path.$key"
                            );

                            $inlineTableString = self::inlineTableString($row);
                            $tb->addValue($key, $inlineTableString);
                        }
                    }
                    else{
                        // Plain TOML array
                        $tb->addValue($key, self::normalizeArray($value));
                    }
                }

            }
            else{
                $tb->addValue($key, self::normalizeValue($value));
            }
        }
    }
    private static function isAssoc(array $arr):bool{
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    private static function isArrayOfTables(array $arr):bool{
        foreach($arr as $item){
            if(!is_array($item) || !self::isAssoc($item)){
                return false;
            }
        }
        return true;
    }
    private static function normalizeValue($value){
        if($value instanceof DateTimeInterface){
            return $value;
        }

        if(is_bool($value) || is_int($value) || is_float($value) || is_string($value)){
            return $value;
        }

        if($value === null){
            mklog(2, "TOML does not support null");
            return false;
        }

        mklog(2, "Unsupported value type: " . gettype($value));
        return false;
    }
    private static function normalizeArray(array $arr):array{
        $out = [];
        foreach($arr as $v){
            if(is_array($v)){
                mklog(2, "Nested arrays inside TOML arrays are not supported here");
                return [];
            }
            $out[] = self::normalizeValue($v);
        }
        return $out;
    }
    private static function inlineTableString(array $data):string{
        $pairs = [];
        foreach($data as $k => $v){
            $v = self::normalizeValue($v);
            if(is_string($v)) $v = '"' . addcslashes($v, '"') . '"';
            elseif($v instanceof \DateTimeInterface) $v = $v->format('Y-m-d\TH:i:s\Z');
            elseif($v === true) $v = 'true';
            elseif($v === false) $v = 'false';
            elseif($v === null) $v = 'null';
            $pairs[] = "$k = $v";
        }
        return '{ ' . implode(', ', $pairs) . ' }';
    }

}