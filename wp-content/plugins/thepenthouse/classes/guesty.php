<?php

class Guesty
{
    private $base_url = "https://api.guesty.com/api/v2/";
    private $user = "1557f8d8d289daced94570a67fd3c81a";
    private $pass = "e029cb29fecb6ef9d3bc315eff7ee26c";    
    private $accountId = "58a5d7f18687ec10007b02c4";

    public function conect($request, $array_to_send = [], $method = 'GET')
    {
        $curl_request = curl_init();
        curl_setopt($curl_request, CURLOPT_URL, $this->base_url . $request);
        curl_setopt($curl_request, CURLOPT_VERBOSE, 1);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, $method);
        if (count($array_to_send) > 0)
            curl_setopt($curl_request, CURLOPT_POSTFIELDS, http_build_query($array_to_send));

        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl_request, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_request, CURLOPT_USERPWD, "$this->user:$this->pass");
        if ($method == 'GET')
            curl_setopt($curl_request, CURLOPT_HTTPHEADER, ['Content-Type: application/json',]);
        $result = curl_exec($curl_request); // execute the request
        $status_code = curl_getinfo($curl_request, CURLINFO_HTTP_CODE);
        curl_close($curl_request);
        $a = json_decode($result, true);
        return ['result' => $a, 'status' => $status_code];
    }

    
    public function reservations($data)
    {
        return $this->conect('/reservations?'.http_build_query($data));
    }

    public function createReservation($data)
    {
        // return $this->conect('/reservations', $data, 'POST');
    }

    public function updateReservation($id, $data)
    {
        // return $this->conect("/reservations/$id", $data, 'PUT');
    }

    public function getListingCalendar($idListing, $from, $to)
    {
        return $this->conect('availability-pricing/api/calendar/listings/' . $idListing .'?startDate=' . $from . '&endDate=' . $to);
    }

    public function createWebhook($data){
        $data = array_merge($data, ['accountId'=>$this->accountId]);
        return $this->conect('/webhooks', $data, 'POST');
    }

}
