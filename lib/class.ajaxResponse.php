<?php

class AjaxResponse
{
    const STATUS_SUCCESS = "SUCCESS";
    const STATUS_ERROR = "ERROR";
    const STATUS_FAIL = "FAIL";

    private $status;
    private $data;
    private $message;


    function __construct($status = AjaxResponse::STATUS_SUCCESS, $message = null, $data = array())
    {
        $this->status = $status;
        $this->data = $data;
        $this->message = $message;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setStatus($status)
    {
        $this->status = $status;
        if ($status == $this::STATUS_FAIL && empty($this->message)) {
            $this->setMessage("Internal error occurred. Please try again later");
        }
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getResponse()
    {
        $response = array();
        $response["status"] = $this->status;
        $response["message"] = $this->message;
        $response["data"] = $this->data;
        header("Content-type: application/json");
        return json_encode($response);
    }
}