<?php

/* This software includes the work that is distributed in the Apache License 2.0. */
/* Copyright 2018 Amazon.com, Inc. or its affiliates. All Rights Reserved. */
/* Licensed under the Apache License, Version 2.0. */

/*
    @version        0.5     a 〜 z連続 検索追加
    @version        0.6     [del]exec_atoz
    @version        0.7     [fix]]細かい修正
    @version        0.8     [fix]]デフォルトパラメーター変更
*/

namespace Akat03\Amazon_pa_api_v5;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

class Amazon_pa_api_v5
{
    private $optionRetryMax;
    private $optionShowRetryError;
    private $optionAccessWait;

    private $optionCache;
    private $optionCacheDir;
    private $optionCacheLifetime;

    private $cache;

    private $serviceName;
    private $region;
    private $accessKey;
    private $secretKey;
    private $uriPath;
    private $target;
    private $partnerTag;
    private $partnerType;
    private $marketplace;

    public $dumpMode;

    public $hasError;
    public $errorMessage;

    public function __construct(array $option = [])
    {
        $this->dumpMode = 0;

        $this->optionRetryMax = (@$option['optionRetryMax']) ? $option['optionRetryMax'] : 10; // x times retry
        $this->optionShowRetryError = true;		// Show Throttle Error Message
        $this->optionAccessWait = (@$option['optionAccessWait']) ? $option['optionAccessWait'] : 1;            // second(s)

        $this->optionCache         = @$option['optionCache'];
        $this->optionCacheDir      = @$option['optionCacheDir'];
        $this->optionCacheLifetime = @$option['optionCacheLifetime'];

        if ( $this->optionCache ){
            $cache_options = array(
                'cacheDir'                => $this->optionCacheDir,
                'caching'                 => 'true',    // キャッシュを有効に
                'automaticSerialization'  => 'true',    // 配列を保存可能に
                'lifeTime'                => $this->optionCacheLifetime,    // 60*30（生存時間：30分）
                'automaticCleaningFactor' => 200,    // 自動で古いファイルを削除（1/200の確率で実行）
                'hashedDirectoryLevel'    => 1,        // ディレクトリ階層の深さ（高速になる）
            );
            $this->cache = new \Cache_Lite( $cache_options );
            // OFF $this->cache = CacheManager::getInstance('files');
        }

        if (empty($option)) {
            throw new Exception('------------引数($option)がありません！！------------');
        }

        $this->serviceName = $option['serviceName'];
        $this->region = $option['region'];
        $this->accessKey = $option['accessKey'];
        $this->secretKey = $option['secretKey'];
        $this->uriPath = $option['uriPath'];
        $this->target = $option['x-amz-target'];
        $this->partnerTag = $option['PartnerTag'];
        $this->partnerType = $option['PartnerType'];
        $this->marketplace = $option['Marketplace'];
    }



    /**
     * Search Keywords A to Z
     *
     * @param   array       $payload_array
     *
     * @return  array       $array
     *
     */
    // public function exec_atoz( array $payload_array = [] )
    // {
    //     $keyword_array = [
    //         // '*'
    //         'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z'
    //         // 'a','e','i','o','u','x','y','z','t','s'
    //     ];

    //     $collection = [];
    //     $out_data = [];

    //     // a - z 検索
    //     foreach ($keyword_array as $keyword) {
    //         $payload_array["Keywords"] = $keyword;
    //         // API exec
    //         $data = $this->exec($payload_array);
    //         $result_array = @$data['SearchResult']['Items'];

    //         // $this->dump( "<h1>{$payload_array["Keywords"]} の結果</h1>" );
    //         foreach ( (array)$result_array as $k => $v ) {
    //             // $this->dump( "{$v['ASIN']} : {$v['ItemInfo']['Title']['DisplayValue']}" );

    //             $push_flag = 1;
    //             foreach ($collection as $c) {
    //                 if ( strcmp($v['ASIN'], $c['ASIN']) == 0 ){
    //                     // すでに登録済み
    //                     $push_flag = 0;
    //                     break;
    //                 }
    //             }

    //             if ( $push_flag == 1 ){
    //                     $collection[] = $v;
    //             }
    //         }
    //     }

    //     // $this->dump( count($collection) );
    //     // $this->dump( $collection );

    //     $out_data = $data;
    //     $out_data['SearchResult']['Items'] = $collection;
    //     return $out_data;
    // }



    /**
     * execute accessing to PA-API V5
     *
     * @param   array       $payload_array
     * @return  array       $array
     *
     */
    public function exec( array $payload_array = [] )
    {
        if ( empty($payload_array) ) {
            throw new Exception('------------引数($payload_array)がありません！！------------');
        }

        $payload_array['PartnerTag'] = $this->partnerTag;
        $payload_array['PartnerType'] = $this->partnerType;
        $payload_array['Marketplace'] = $this->marketplace;

        $payload = json_encode($payload_array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // get Cache
        $cache_id = sha1( $payload );
        $cache_exists = 0;
        $array = [];
        if( $this->optionCache == 1 ){
            list($cache_exists, $array) = $this->_get_cache( $cache_id );
		}
        if ( $cache_exists == 1 ){
            $array['_cache_matched'] = 1;
			return $array;
		}


        $host = "webservices.amazon.co.jp";
        $uriPath = $this->uriPath;
        $awsv4 = new AwsV4($this->accessKey, $this->secretKey);
        $awsv4->setRegionName($this->region);
        $awsv4->setServiceName($this->serviceName);
        $awsv4->setPath($uriPath);
        $awsv4->setPayload($payload);
        $awsv4->setRequestMethod("POST");
        $awsv4->addHeader('content-encoding', 'amz-1.0');
        $awsv4->addHeader('content-type', 'application/json; charset=utf-8');
        $awsv4->addHeader('host', $host);
        $awsv4->addHeader('x-amz-target', $this->target);
        $headers = $awsv4->getHeaders();
        $headerString = "";
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }

        // $params = array(
        //     'http' => array(
        //         'header' => $headerString,
        //         'method' => 'POST',
        //         'content' => $payload,
        //     ),
        // );
        // $stream = stream_context_create($params);

        $url = 'https://' . $host . $uriPath;


        // 2. guzzle
        // ===== guzzle handler
        // $handler_stack = HandlerStack::create();
        // $handler_stack->push(Middleware::mapRequest(function (RequestInterface $request) {
        //     return $request->withoutHeader('User-Agent');
        // }));
        // $client = new Client([
        //     'handler' => $handler_stack
        // ]);
        // ===== guzzle handler

        $client = new \GuzzleHttp\Client([
            // [\GuzzleHttp\RequestOptions::VERIFY => false]
        ]);

        $this->hasError = false;
        $this->errorMessage = null;

        $response = null;
        $data     = '';
        $i=1;

        for ($i=1; $i <= $this->optionRetryMax; $i++) {
			$my_exec_time = microtime(true);
            $now_time = $this->format_microtime($my_exec_time,"e T P : Y-m-d H:i:s");
            if ($this->dumpMode == 1){ $this->dump( $i." : TRY: ({$now_time})" ); }
            $response = $client->post($url, [
                'http_errors' => false ,
                // 'debug'   => true ,
                'headers' => $headers,
                'body'    => $payload,
            ] );
            $data = (string) $response->getBody();
	        $array = json_decode($data, true);

            // on Error
            if ( isset($array['Errors']) ){
                // $this->dump($array['Errors']);
                if ( $array['Errors'][0]['Code'] === 'TooManyRequests' ){
                    if ( $this->optionShowRetryError ){
                        print("<div style='font-weight:bold;'>API ERROR: TooManyRequests: retry after " .$this->optionAccessWait. " second(s). </div>\n");
                        // $this->dump( $this->optionAccessWait );
                        sleep( $this->optionAccessWait );
                    }
                }
                elseif ( $array['Errors'][0]['Code'] === 'NoResults' ){
                    // Error
                    $this->hasError = true;
                    $this->errorMessage = 'Amazon_pa_api_v5 ERROR: ' . $array['Errors'][0]['Message'];
                    return $array;
                }
                elseif ( $array['Errors'][0]['Code'] === 'InvalidParameterValue' ||
                         $array['Errors'][0]['Code'] === 'ItemNotAccessible'
                ){
                    // Error
                    $this->hasError = true;
                    $this->errorMessage = 'Amazon_pa_api_v5 ERROR: ' . $array['Errors'][0]['Message'];
                    return $array;
                }
                else {
                    $this->hasError = true;
                    $this->errorMessage = $array['Errors'][0]['Message'];
                    throw new \Exception("API ERROR: Code: " . $array['Errors'][0]['Code'] . ' Message: '. $array['Errors'][0]['Message']);
                    die;
                }
            }
            // on Success
            else {
                break;
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
        	throw new \Exception("JSON DECODE ERROR in API json response");
        }

        if ( strcmp($data, '') == 0 ){
            $this->dump( $i );
            $this->dump( $this->optionRetryMax );
            die('TooManyRequests error');
        
        }




        // set cache
		if( $this->optionCache == 1 ){

            $this->dump( "キャッシュが有効です" );

            $rt = $this->cache->save($array,$cache_id);

            $this->dump( $rt,'rt' );
            $this->dump( $array,'array' );
            $this->dump( $cache_id,'cache_id' );

            if( !$rt ){
                $this->dump( "Amazon_pa_api Cache Save Error : Check this directory -> [{$this->optionCacheDir}]" );
            }
            if ( $this->cache ){
                if( $this->dumpMode == 1 ){
                    $this->dump( "● Cache file saved. : {$cache_id}" );
                }
            }
		}

        // if ( $this->optionCache ){
        //     // cache
        //     phpFastCache::$storage = "files";
        //     phpFastCache::$path = $this->optionCacheDir;

        //     $cache_id = sha1( serialize($array) );
        //     $this->dump( $cache_id ); die;
        //     if($array != null) {
        //         phpFastCache::set($cache_id, $array, $this->optionCacheLifetime);
        //     }
        // }

        return $array;

    }



    /**
     * 10件までまとめて検索する
     *
     * @param   array       $payload_array
     * @return  array       $array
     *
     */
    public function exec_multi( array $payload_array = [] )
    {
        if ( empty($payload_array) ) {
            throw new Exception('------------引数($payload_array)がありません！！------------');
        }

        $payload_array['PartnerTag'] = $this->partnerTag;
        $payload_array['PartnerType'] = $this->partnerType;
        $payload_array['Marketplace'] = $this->marketplace;

        $payload = json_encode($payload_array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // get Cache
        $cache_id = sha1( $payload );
        $cache_exists = 0;
        $array = [];
        if( $this->optionCache == 1 ){
            list($cache_exists, $array) = $this->_get_cache( $cache_id );
		}
        if ( $cache_exists == 1 ){
            $array['_cache_matched'] = 1;
			return $array;
		}


        $host = "webservices.amazon.co.jp";
        $uriPath = $this->uriPath;
        $awsv4 = new AwsV4($this->accessKey, $this->secretKey);
        $awsv4->setRegionName($this->region);
        $awsv4->setServiceName($this->serviceName);
        $awsv4->setPath($uriPath);
        $awsv4->setPayload($payload);
        $awsv4->setRequestMethod("POST");
        $awsv4->addHeader('content-encoding', 'amz-1.0');
        $awsv4->addHeader('content-type', 'application/json; charset=utf-8');
        $awsv4->addHeader('host', $host);
        $awsv4->addHeader('x-amz-target', $this->target);
        $headers = $awsv4->getHeaders();
        $headerString = "";
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }

        $url = 'https://' . $host . $uriPath;

        $client = new \GuzzleHttp\Client([
            // [\GuzzleHttp\RequestOptions::VERIFY => false]
        ]);

        $this->hasError = false;
        $this->errorMessage = null;

        $response = null;
        $data     = '';
        $i=1;

        for ($i=1; $i <= $this->optionRetryMax; $i++) {
			$my_exec_time = microtime(true);
            $now_time = $this->format_microtime($my_exec_time,"e T P : Y-m-d H:i:s");
            if ($this->dumpMode == 1){ $this->dump( $i." : TRY: ({$now_time})" ); }
            $response = $client->post($url, [
                'http_errors' => false ,
                // 'debug'   => true ,
                'headers' => $headers,
                'body'    => $payload,
            ] );
            $data = (string) $response->getBody();
	        $array = json_decode($data, true);

            // on Error
            if ( isset($array['Errors']) ){
                if ( $array['Errors'][0]['Code'] === 'TooManyRequests' ){
                    if ( $this->optionShowRetryError ){
                        print("<div style='font-weight:bold;'>API ERROR: TooManyRequests: retry after " .$this->optionAccessWait. " second(s). </div>\n");
                        sleep( $this->optionAccessWait );
                    }
                }
                elseif ( $array['Errors'][0]['Code'] === 'NoResults' ){
                    // Error
                    $this->hasError = true;
                    $this->errorMessage = 'Amazon_pa_api_v5 ERROR: ' . $array['Errors'][0]['Message'];
                    return $array;
                }
                elseif ( $array['Errors'][0]['Code'] === 'InvalidParameterValue' ||
                         $array['Errors'][0]['Code'] === 'ItemNotAccessible'
                ){
                    // Error
                    // $this->hasError = true;
                    // $this->errorMessage = 'Amazon_pa_api_v5 ERROR: ' . $array['Errors'][0]['Message'];
                    // return $array;
                    break;
                }
                else {
                    $this->hasError = true;
                    $this->errorMessage = $array['Errors'][0]['Message'];
                    throw new \Exception("API ERROR: Code: " . $array['Errors'][0]['Code'] . ' Message: '. $array['Errors'][0]['Message']);
                    die;
                }
            }
            // on Success
            else {
                break;
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
        	throw new \Exception("JSON DECODE ERROR in API json response");
        }

        if ( strcmp($data, '') == 0 ){
            $this->dump( $i );
            $this->dump( $this->optionRetryMax );
            die('TooManyRequests error');
        }

        // 返却配列の作成
        $out_array = [];
        if ( isset($array['Errors']) ){
            $out_array['Errors'] = $array['Errors'];
        }

        $out_array['result_ASINs'] = [];
        $out_array['ASINs'] = [];

        // if ( $array['ItemsResult']['Items'] !== null ){
            foreach ( $array['ItemsResult']['Items'] as $v ) {
                $asin = $v['ASIN'];
                array_push( $out_array['result_ASINs'], $asin );
                $out_array['ASINs'][$asin] = $v;
            }
        // }



        // set cache
		if( $this->optionCache == 1 ){
            $rt = $this->cache->save($out_array,$cache_id);
            if( !$rt ){
                $this->dump( "Amazon_pa_api Cache Save Error : Check this directory -> [{$this->optionCacheDir}]" );
            }
            if ( $this->cache ){
                if( $this->dumpMode == 1 ){
                    $this->dump( "● Cache file saved. : {$cache_id}" );
                }
            }
		}

        // if ( $this->optionCache ){
        //     // cache
        //     phpFastCache::$storage = "files";
        //     phpFastCache::$path = $this->optionCacheDir;

        //     $cache_id = sha1( serialize($array) );
        //     $this->dump( $cache_id ); die;
        //     if($array != null) {
        //         phpFastCache::set($cache_id, $array, $this->optionCacheLifetime);
        //     }
        // }

        return $out_array;

    }




    /**
     * Get Cache
     *
     * @param   string         $cache_id
     *
     * @return array           [ result_code(1 or 0), $cache_data ]
     *
     */
    function _get_cache( string $cache_id = '' )
    {
		if ( strcmp($cache_id,'')==0 ){ die("exaws ERROR : please set cache_id"); }

        // キャッシュデータがあるかどうかの判別
        $cache_data = null;
        $cache_data = $this->cache->get($cache_id);
		if( $cache_data != false ){
            if( $this->dumpMode == 1 ){
                $this->dump('Cache matched: ',"_get_cache('{$cache_id}')");
                // $this->dump( $cache_data,'Cache Data' );
			}
            return array(1, $cache_data);
		}
		else{
			return array(0, false);
		}
	}



    /**
     * dump method
     *
     * @param   mix            $arg
     * @param   string         $title
     */
    function dump( $data, string $title='' )
    {
        print "\n".'<pre style="position:relative; text-align:left;border: solid red 1px; padding: 10px; margin: 10px 0; background:#fafafa; overflow:scroll; width:95%; max-height:80vh;">'."\n";
        print_r($data);
        if ( $title ){
            print "<span style='border: solid #777 1px; padding: 5px;position:absolute;top:5px;right:0;'>{$title}</span>";
        }
        print "</pre>\n\n";
    }



    /**
     * dump method
     *
     * @param   mix         $arg
     */
    public function vdump()
    {
        // // 全引数の var_dump() の出力内容を変数に取り出し
        // ob_start();
        // foreach (func_get_args() as $arg) {
        //     var_dump($arg);
        // }
        // $dump = ob_get_clean();

        // // 可読性のためインデント幅を2倍に (2 -> 4)
        // $dump = preg_replace_callback(
        //     '/^\s++/m',
        //     function ($m) {
        //         return str_repeat(" ", strlen($m[0]) * 2);
        //     },
        //     $dump
        // );

        // // この関数の呼び出し元を取得 （ファイルパス・行番号）
        // $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        // // ヘッダーの HTML を生成
        // $header = sprintf('<pre style="%s">', implode(';', [
        //     'overflow: scroll',
        //     'margin: 10px 0 0 0',
        //     'border: 1px solid #bbb',
        //     'padding: 6px',
        //     'solid: #bbb',
        //     'text-align: left',
        //     'background: #fdfdfd',
        //     'color: #000',
        //     'font-family: monospace,serif',
        //     'font-size: 13px',
        // ]));
        // $header .= sprintf('<span style="%s">%s:%d</span>',
        //     implode(';', ['font-weight: bold']),
        //     $caller['file'],
        //     $caller['line']
        // );
        // $header .= PHP_EOL;

        // // フッターの HTML を生成
        // $footer = '</pre>' . PHP_EOL;

        // // ダンプ内容を出力 (CLI で実行された場合は HTML タグを取り除く)
        // $isCli = (php_sapi_name() === 'cli');
        // echo $isCli ? strip_tags($header) : $header;
        // echo $dump;
        // echo $isCli ? strip_tags($footer) : $footer;
    }


    private function format_microtime ( $time, $format = null )
	{
	   if (is_string($format)) {
	            $sec  = (int)$time;
	         $msec = (int)(($time - $sec) * 100000);
	            $formated = date($format, $sec). '.'. $msec;
	     } else {
	         $formated = sprintf('%0.5f', $time);
	    }
	    return $formated;
	}




}



class AwsV4
{

    private $accessKey = null;
    private $secretKey = null;
    private $path = null;
    private $regionName = null;
    private $serviceName = null;
    private $httpMethodName = null;
    private $queryParametes = array();
    private $awsHeaders = array();
    private $payload = "";

    private $HMACAlgorithm = "AWS4-HMAC-SHA256";
    private $aws4Request = "aws4_request";
    private $strSignedHeader = null;
    private $xAmzDate = null;
    private $currentDate = null;

    public function __construct($accessKey, $secretKey)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->xAmzDate = $this->getTimeStamp();
        $this->currentDate = $this->getDate();
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function setServiceName($serviceName)
    {
        $this->serviceName = $serviceName;
    }

    public function setRegionName($regionName)
    {
        $this->regionName = $regionName;
    }

    public function setPayload($payload)
    {
        $this->payload = $payload;
    }

    public function setRequestMethod($method)
    {
        $this->httpMethodName = $method;
    }

    public function addHeader($headerName, $headerValue)
    {
        $this->awsHeaders[$headerName] = $headerValue;
    }

    private function prepareCanonicalRequest()
    {
        $canonicalURL = "";
        $canonicalURL .= $this->httpMethodName . "\n";
        $canonicalURL .= $this->path . "\n" . "\n";
        $signedHeaders = '';
        foreach ($this->awsHeaders as $key => $value) {
            $signedHeaders .= $key . ";";
            $canonicalURL .= $key . ":" . $value . "\n";
        }
        $canonicalURL .= "\n";
        $this->strSignedHeader = substr($signedHeaders, 0, -1);
        $canonicalURL .= $this->strSignedHeader . "\n";
        $canonicalURL .= $this->generateHex($this->payload);
        return $canonicalURL;
    }

    private function prepareStringToSign($canonicalURL)
    {
        $stringToSign = '';
        $stringToSign .= $this->HMACAlgorithm . "\n";
        $stringToSign .= $this->xAmzDate . "\n";
        $stringToSign .= $this->currentDate . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "\n";
        $stringToSign .= $this->generateHex($canonicalURL);
        return $stringToSign;
    }

    private function calculateSignature($stringToSign)
    {
        $signatureKey = $this->getSignatureKey($this->secretKey, $this->currentDate, $this->regionName, $this->serviceName);
        $signature = hash_hmac("sha256", $stringToSign, $signatureKey, true);
        $strHexSignature = strtolower(bin2hex($signature));
        return $strHexSignature;
    }

    public function getHeaders()
    {
        $this->awsHeaders['x-amz-date'] = $this->xAmzDate;
        ksort($this->awsHeaders);

        // Step 1: CREATE A CANONICAL REQUEST
        $canonicalURL = $this->prepareCanonicalRequest();

        // Step 2: CREATE THE STRING TO SIGN
        $stringToSign = $this->prepareStringToSign($canonicalURL);

        // Step 3: CALCULATE THE SIGNATURE
        $signature = $this->calculateSignature($stringToSign);

        // Step 4: CALCULATE AUTHORIZATION HEADER
        if ($signature) {
            $this->awsHeaders['Authorization'] = $this->buildAuthorizationString($signature);
            return $this->awsHeaders;
        }
    }

    private function buildAuthorizationString($strSignature)
    {
        return $this->HMACAlgorithm . " " . "Credential=" . $this->accessKey . "/" . $this->getDate() . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "," . "SignedHeaders=" . $this->strSignedHeader . "," . "Signature=" . $strSignature;
    }

    private function generateHex($data)
    {
        return strtolower(bin2hex(hash("sha256", $data, true)));
    }

    private function getSignatureKey($key, $date, $regionName, $serviceName)
    {
        $kSecret = "AWS4" . $key;
        $kDate = hash_hmac("sha256", $date, $kSecret, true);
        $kRegion = hash_hmac("sha256", $regionName, $kDate, true);
        $kService = hash_hmac("sha256", $serviceName, $kRegion, true);
        $kSigning = hash_hmac("sha256", $this->aws4Request, $kService, true);

        return $kSigning;
    }

    private function getTimeStamp()
    {
        return gmdate("Ymd\THis\Z");
    }

    private function getDate()
    {
        return gmdate("Ymd");
    }
}
