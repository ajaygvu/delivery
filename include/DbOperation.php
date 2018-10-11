<?php

class DbOperation
{
    private $con;

    function __construct()
    {
        require_once dirname(__FILE__) . '/DbConnect.php';
        $db = new DbConnect();
        $this->con = $db->connect();
    }
	
    //Method to create a new order
    public function createOrder($params){
        $distance = "";
        $distance = $this->isDistanceExists($params);
        if (!isset($distance) || $distance == "") {
            $distance = $this->getDistance($params);
            if(!$distance){
                return 0;
            }
        }

        $stmt = $this->con->prepare("INSERT INTO distance_map(start_latitude, start_longitude, end_latitude, end_longitude, distance) values(?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss",$params['start_latitude'], $params['start_longitude'], $params['end_latitude'], $params['end_longitude'], $distance);
        $stmt->execute();
        $stmt->close();
        $thelastid=mysqli_insert_id($this->con);
        $returnParams = "";

        $this->makeOrderEntry($thelastid, $distance);

        $returnParams['id'] = $thelastid;
        $returnParams['distance'] = $distance;
        $returnParams['status'] = 'UNASSIGN';
        return $returnParams;
    }

    //Method to make entry as unassign order
    private function makeOrderEntry($id, $distance, $status=0){
        $stmt = $this->con->prepare("INSERT INTO order_map(id, status, distance) values(?, ?, ?)");
        $stmt->bind_param("sss", $id, $status, $distance);
        $stmt->execute();
    }
    
    /**
     * get the distance from api
     */
    private function getDistance($params){
        $origin = $params['start_latitude'] .",". $params['start_longitude'];
        $destination = $params['end_latitude'] .",". $params['end_longitude'];
        $api = file_get_contents("https://maps.googleapis.com/maps/api/distancematrix/json?units=imperial&origins=" . $origin . "&destinations=" . $destination . "&key=" . GOOGLE_API_KEY);
        $data = json_decode($api);

        if ($data->rows[0]->elements[0]->status == 'NOT_FOUND' || !$data)
            return false;

        return ((int) $data->rows[0]->elements[0]->distance->value / 1000) . ' Km';
    }

    //Method to update order status
    public function updateOrder($id){
        $orderStatus = $this->getOrderStatus($id);
        if(!is_null($orderStatus) && $orderStatus == 0){
            $stmt = $this->con->prepare("UPDATE order_map SET status = 1 WHERE id=?");
            $stmt->bind_param("i",$id);
            $result = $stmt->execute();
            $stmt->close();
            if($result){
                return 1;
            }
            return false;
        } elseif ($orderStatus == 1) {
            return 0;
        } else {
            return 2;
        } 
    }

    //Method to get the order status
    public function getOrderStatus($id){
        $stmt = $this->con->prepare("SELECT status FROM order_map WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $assign = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $assign['status'];
    }

    //Method to get all the orders
    public function getOrders($startFrom, $limit){
        if(isset($startFrom, $limit)){
            $stmt = $this->con->prepare("SELECT * FROM order_map WHERE 1 LIMIT ?,?");
            $stmt->bind_param("ss",$startFrom,$limit);
        }else if(isset($limit)){
            $stmt = $this->con->prepare("SELECT * FROM order_map WHERE 1 LIMIT ?");
            $stmt->bind_param("s",$limit);
        }else{
            $stmt = $this->con->prepare("SELECT * FROM order_map");
        }
        $stmt->execute();
        $orders = $stmt->get_result();
        $stmt->close();
        return $orders;
    }

    //Method to check distance for the given latitudes and longitudes already exists or not
    public function isDistanceExists($params) {
        $stmt = $this->con->prepare("SELECT distance from distance_map where start_latitude = ? AND start_longitude = ? AND end_latitude = ? AND end_longitude = ?");
        $stmt->bind_param("ssss",$params['start_latitude'], $params['start_longitude'], $params['end_latitude'], $params['end_longitude']);
        $stmt->execute();
        $distance = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $distance['distance'];
    }
}