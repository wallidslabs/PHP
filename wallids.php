<?php

function Wallids($secret_key, $monitoring = 'off')
{
#region VERIABLES
    $targetUrl       = "https://api.wallids.com/tracing/client";
    $logRequestModel = new stdClass();
    $infoModel       = new stdClass();

#region INIT
    $logRequestModel->secretKey = $secret_key;
    $logRequestModel->scheme = $_SERVER['REQUEST_SCHEME'];

    $infoModel->url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $infoModel->requestType = $_SERVER['REQUEST_METHOD'];
    $infoModel->headers = getallheaders();

#region GET REQUEST DATA

    $json_string = json_encode($_POST);
    $requestData = json_decode(file_get_contents("php://input"));

    if (is_null($requestData)) {
        parse_str(file_get_contents("php://input"), $data);
        $data = (object)$data;

        $requestData = $data;
    }

    if ($requestData == new stdClass()) {

        $postData = $_POST;
        parse_str(file_get_contents('php://input'), $requestData);
        $postData = (object)$postData;

        $requestData = $postData;
    }

#region LOAD
    $infoModel->formDatas = $requestData;

    $virtualPost = false;

    if ($_SERVER['REQUEST_METHOD'] == "POST" || $_SERVER['REQUEST_METHOD'] == "PUT") {
        $infoModel->request = $requestData;
    } else {
        if (strlen($_SERVER['QUERY_STRING']) == 0) {
            $infoModel->request = $infoModel->url;
        } else {
            $infoModel->requestType = "POST";
            parse_str($_SERVER['QUERY_STRING'], $queryStringArr);
            $infoModel->request = $queryStringArr;
            $virtualPost = true;
        }
    }

    $infoModel->ip = $_SERVER['REMOTE_ADDR'];

    $infoModel->responseData = "";
    $infoModel->statusCode = 0;
    $infoModel->errorMessage = "";
    $infoModel->isMonitoring = $monitoring;
    $logRequestModel->info = $infoModel;

#region SEND
    $ch = curl_init($targetUrl);
    $payload = json_encode($logRequestModel);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

#region PARSE
    $apiResult = json_decode($response, true);
    $urlArr = parse_url($infoModel->url);

    $newUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" . "://$_SERVER[HTTP_HOST]" . $urlArr['path'];

    if ($monitoring == "off") {
        if ($apiResult['attack'] != 0) {
            if ($virtualPost) {
                $_SERVER['QUERY_STRING'] = http_build_query($apiResult['body']);
                $newUrl = $newUrl . "?" . $_SERVER['QUERY_STRING'];
                //header('Location: ' . $newURL);
                //die();
            } else {
                if ($_SERVER['REQUEST_METHOD'] == "GET") {
                    //header('Location: ' . $apiResult['body']);
                    die();
                } else {
                    $_POST = $apiResult['body'];
                }
            }
        }
    }
}
