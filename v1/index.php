<?php

//including the required files
require_once '../include/DbOperation.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

/* *
 * URL: http://localhost/delivery/v1/order
 * Parameters: origin, destination
 * Method: POST
 * */
$app->post('/order', function () use ($app) {
    $request_params = verifyRequiredParams(array('origin', 'destination'));
    $response = array();
    $params = array();
    $params['start_latitude'] = $request_params['origin']['0'];
    $params['start_longitude'] = $request_params['origin']['1'];
    $params['end_latitude'] = $request_params['destination']['0'];
    $params['end_longitude'] = $request_params['destination']['1'];
    $flag = validateLatLong($params);

    if($flag){
        $db = new DbOperation();
        $res = $db->createOrder($params);
        if ($res == 0) {
            $response["error"] = false;
            $response["message"] = "Order not created successfully";
            echoResponse(200, $response);
        } else {
            $response['id'] = $res['id'];
            $response['distance'] = $res['distance'];;
            $response['status'] = $res['status'];
            echoResponse(200, $response);
        }
    }else{
        $response["error"] = "Entered data is not valid";
        echoResponse(500, $response);
    }
});

/* *
 * URL: http://localhost/delivery/v1/orders
 * Parameters: none
 * Authorization: Put API Key in Request Header
 * Method: GET
 * */
$app->get('/orders', 'authenticate', function() use ($app){
    $page = $app->request->get('page');
    $limit = $app->request->get('limit');
    if(!isset($page, $limit)){
        $page = 0;
        $limit = 10;
    }
    $startFrom = $page * $limit;
    $db = new DbOperation();
    $result = $db->getOrders($startFrom, $limit);
    $response = array();
    while($row = $result->fetch_assoc()){
        $temp = array();
        $temp['id']=$row['id'];
        $temp['distance'] = $row['distance'];
        $temp['status'] = ($row['status'])?'ASSIGN':'UNASSIGN';
        array_push($response,$temp);
    }
    echoResponse(200,$response);
});

/* *
 * URL: http://localhost/delivery/v1/order/<order_id>
 * Parameters: none
 * Authorization: Put API Key in Request Header
 * Method: PUT
 * */
$app->put('/order/:id', 'authenticate', function($order_id) use ($app){
    $request_params = verifyRequiredParams(array('status'));
    if($request_params['status'] == 'taken'){
        $db = new DbOperation();
        $result = $db->updateOrder($order_id);
        $response = array();
        if($result == 2){
            $response['error'] = "INVALID_ORDER_ID";
            echoResponse(401,$response);
        }else if($result == 0){
            $response['error'] = "ORDER_ALREADY_BEEN_TAKEN";
            echoResponse(409,$response);
        }else if($result == 1){
            $response['status'] = "SUCCESS";
            echoResponse(200,$response);
        }else{
            $response['error'] = "ERROR OCCURED";
            echoResponse(500,$response);
        }
    } else {
        $response["error"] = "Entered data is not valid";
        echoResponse(500, $response);
    }
});

/**
* Sending API Response
*/
function echoResponse($status_code, $response)
{
    $app = \Slim\Slim::getInstance();
    $app->status($status_code);
    $app->contentType('application/json');
    echo json_encode($response);
}

/**
* Verifying Request Method and Request Params
*/
function verifyRequiredParams($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = $_REQUEST;

    if ($_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'POST') {
        $app = \Slim\Slim::getInstance();
        $request_params = json_decode($app->request()->getBody(), True);
    }

    foreach ($required_fields as $field) {
        //if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
        if (empty($request_params[$field])){
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        $response = array();
        $app = \Slim\Slim::getInstance();
        //$response["error"] = true;
        $response["error"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoResponse(500, $response);
        $app->stop();
    }else{
        return $request_params;
    }
}

/**
* Validating inputs
* @param array $params
* 
* @return bool
*/
function validateLatLong($params) {
    if($params['start_latitude'] <= -90 && $params['start_latitude'] >= 90) {
        return false;
    }

    if($params['end_latitude'] <= -90 && $params['end_latitude'] >= 90) {
        return false;
    }

    if($params['start_longitude'] <= -180 && $params['start_longitude'] >= 180) {
        return false;
    }

    if($params['end_longitude'] <= -180 && $params['end_longitude'] >= 180) {
        return false;
    }
    if(!is_numeric($params['start_latitude'])){
        return false;
    }
    if(!is_numeric($params['end_latitude'])){
        return false;
    }
    if(!is_numeric($params['start_longitude'])){
        return false;
    }
    if(!is_numeric($params['end_longitude'])){
        return false;
    }

    if($params['start_latitude'] == $params['end_latitude'] ||
        $params['start_longitude'] == $params['end_longitude'] ||
        $params['start_latitude'] == $params['start_longitude'] ||
        $params['end_latitude'] == $params['end_longitude']
    )
    {
        return false;
    }

    if($params['start_latitude'] == "" ||
        $params['start_longitude'] == "" ||
        $params['start_latitude'] == "" ||
        $params['end_latitude'] == ""
    )
    {
        return false;
    }

    if((trim($params['start_latitude'],'0') != (float)$params['start_latitude']) && 
        (trim($params['start_longitude'],'0') != (float)$params['start_longitude'])
    )
    {
        return false;
    }

    if((trim($params['end_latitude'],'0') != (float)$params['end_latitude']) && 
        (trim($params['end_longitude'],'0') != (float)$params['end_longitude'])
    )
    {
        return false;
    }
    return true;
}

/**
* Authenticating API Hits
*/
function authenticate(\Slim\Route $route)
{
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
    if (isset($headers['Authorization'])) {
        $db = new DbOperation();
        $api_key = $headers['Authorization'];
        if (!isValidHit($api_key)) {
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoResponse(401, $response);
            $app->stop();
        }
    } else {
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoResponse(400, $response);
        $app->stop();
    }
}

/**
* Matching API Access Token
*/
function isValidHit($key){
    $accessToken = 'Bearer '.ACCESS_TOKEN;
    if($key == $accessToken){
        return true;
    }else{
        return false;
    }
}

$app->run();