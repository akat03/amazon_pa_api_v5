<?php

/* This software includes the work that is distributed in the Apache License 2.0. */
/* Copyright 2018 Amazon.com, Inc. or its affiliates. All Rights Reserved. */
/* Licensed under the Apache License, Version 2.0. */

namespace Akat03\Amazon_pa_api_v5;

class Amazon_pa_api_v5 {

    // Put your Secret Key in place of **********

    private $serviceName;
    private $region;
    private $accessKey;
    private $secretKey;
    private $uriPath;
    private $target;
    private $partnerTag;
    private $partnerType;
    private $marketplace;

    public function __construct(array $option = [])	{
        if (empty($option)) {
            throw new Exception ( '------------引数($option)がありません！！------------' );
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

    function exec(array $payload_array = []) {
        if (empty($payload_array['payload'])) {
            throw new Exception ( '------------引数($payload_array)がありません！！------------' );
        }

        $payload_array['payload']['PartnerTag'] = $this->partnerTag;
        $payload_array['payload']['PartnerType'] = $this->partnerType;
        $payload_array['payload']['Marketplace'] = $this->marketplace;
        

        $payload = json_encode( $payload_array['payload'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT );
        $host="webservices.amazon.co.jp";
        $uriPath= $this->uriPath;
        $awsv4 = new AwsV4 ($this->accessKey, $this->secretKey);
        $awsv4->setRegionName($this->region);
        $awsv4->setServiceName($this->serviceName);
        $awsv4->setPath ($uriPath);
        $awsv4->setPayload ($payload);
        $awsv4->setRequestMethod ("POST");
        $awsv4->addHeader ('content-encoding', 'amz-1.0');
        $awsv4->addHeader ('content-type', 'application/json; charset=utf-8');
        $awsv4->addHeader ('host', $host);
        $awsv4->addHeader ('x-amz-target', $this->target);
        $headers = $awsv4->getHeaders ();
        $headerString = "";
        foreach ( $headers as $key => $value ) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }
        $params = array (
                'http' => array (
                    'header' => $headerString,
                    'method' => 'POST',
                    'content' => $payload
                )
            );
        $stream = stream_context_create ( $params );

        // kato
        $params = array (
            'http' => array (
                'header' => $headerString,
                'method' => 'POST',
                'content' => $payload
            ) ,
            'ssl' => [
                'verify_peer'      => false ,
                'verify_peer_name' => false ,
            ] ,
        );
        $stream = stream_context_create ( $params );
        $url = 'https://'.$host.$uriPath;
        $data = file_get_contents($url , false , $stream);

        // dump($data);   // var_dump の 代わりのダンプ関数

        $array = json_decode( $data, true );
        if (json_last_error() !== JSON_ERROR_NONE) {
            die( "JSON DECODE ERROR in FILE-NAME.json" );
        }
        return $array;   // var_dump の 代わりのダンプ関数
        // kato
    }
}


class AwsV4 {

    private $accessKey = null;
    private $secretKey = null;
    private $path = null;
    private $regionName = null;
    private $serviceName = null;
    private $httpMethodName = null;
    private $queryParametes = array ();
    private $awsHeaders = array ();
    private $payload = "";

    private $HMACAlgorithm = "AWS4-HMAC-SHA256";
    private $aws4Request = "aws4_request";
    private $strSignedHeader = null;
    private $xAmzDate = null;
    private $currentDate = null;

    public function __construct($accessKey, $secretKey) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->xAmzDate = $this->getTimeStamp ();
        $this->currentDate = $this->getDate ();
    }

    function setPath($path) {
        $this->path = $path;
    }

    function setServiceName($serviceName) {
        $this->serviceName = $serviceName;
    }

    function setRegionName($regionName) {
        $this->regionName = $regionName;
    }

    function setPayload($payload) {
        $this->payload = $payload;
    }

    function setRequestMethod($method) {
        $this->httpMethodName = $method;
    }

    function addHeader($headerName, $headerValue) {
        $this->awsHeaders [$headerName] = $headerValue;
    }

    private function prepareCanonicalRequest() {
        $canonicalURL = "";
        $canonicalURL .= $this->httpMethodName . "\n";
        $canonicalURL .= $this->path . "\n" . "\n";
        $signedHeaders = '';
        foreach ( $this->awsHeaders as $key => $value ) {
            $signedHeaders .= $key . ";";
            $canonicalURL .= $key . ":" . $value . "\n";
        }
        $canonicalURL .= "\n";
        $this->strSignedHeader = substr ( $signedHeaders, 0, - 1 );
        $canonicalURL .= $this->strSignedHeader . "\n";
        $canonicalURL .= $this->generateHex ( $this->payload );
        return $canonicalURL;
    }

    private function prepareStringToSign($canonicalURL) {
        $stringToSign = '';
        $stringToSign .= $this->HMACAlgorithm . "\n";
        $stringToSign .= $this->xAmzDate . "\n";
        $stringToSign .= $this->currentDate . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "\n";
        $stringToSign .= $this->generateHex ( $canonicalURL );
        return $stringToSign;
    }

    private function calculateSignature($stringToSign) {
        $signatureKey = $this->getSignatureKey ( $this->secretKey, $this->currentDate, $this->regionName, $this->serviceName );
        $signature = hash_hmac ( "sha256", $stringToSign, $signatureKey, true );
        $strHexSignature = strtolower ( bin2hex ( $signature ) );
        return $strHexSignature;
    }

    public function getHeaders() {
        $this->awsHeaders ['x-amz-date'] = $this->xAmzDate;
        ksort ( $this->awsHeaders );

        // Step 1: CREATE A CANONICAL REQUEST
        $canonicalURL = $this->prepareCanonicalRequest ();

        // Step 2: CREATE THE STRING TO SIGN
        $stringToSign = $this->prepareStringToSign ( $canonicalURL );

        // Step 3: CALCULATE THE SIGNATURE
        $signature = $this->calculateSignature ( $stringToSign );

        // Step 4: CALCULATE AUTHORIZATION HEADER
        if ($signature) {
            $this->awsHeaders ['Authorization'] = $this->buildAuthorizationString ( $signature );
            return $this->awsHeaders;
        }
    }

    private function buildAuthorizationString($strSignature) {
        return $this->HMACAlgorithm . " " . "Credential=" . $this->accessKey . "/" . $this->getDate () . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "," . "SignedHeaders=" . $this->strSignedHeader . "," . "Signature=" . $strSignature;
    }

    private function generateHex($data) {
        return strtolower ( bin2hex ( hash ( "sha256", $data, true ) ) );
    }

    private function getSignatureKey($key, $date, $regionName, $serviceName) {
        $kSecret = "AWS4" . $key;
        $kDate = hash_hmac ( "sha256", $date, $kSecret, true );
        $kRegion = hash_hmac ( "sha256", $regionName, $kDate, true );
        $kService = hash_hmac ( "sha256", $serviceName, $kRegion, true );
        $kSigning = hash_hmac ( "sha256", $this->aws4Request, $kService, true );

        return $kSigning;
    }

    private function getTimeStamp() {
        return gmdate ( "Ymd\THis\Z" );
    }

    private function getDate() {
        return gmdate ( "Ymd" );
    }
}





