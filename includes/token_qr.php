<?php
require_once __DIR__.'/../vendor/autoload.php';

function token_qr_data_uri(string|array $token): string{
    static $tokenRecords=[];
    static $qrCache=[];
    if(is_string($token)){
        $tokenNo=$token;
        if(!array_key_exists($tokenNo,$tokenRecords)){
            global $conn;
            $stmt=$conn->prepare('SELECT t.token_no,t.full_name,COALESCE(NULLIF(t.address,""),p.address) AS address,COALESCE(NULLIF(t.district,""),p.district) AS district,t.service_type,t.preferred_date,t.preferred_time FROM tokens t LEFT JOIN passenger_profiles p ON p.user_id=t.user_id WHERE t.token_no=? LIMIT 1');
            if($stmt){
                $stmt->bind_param('s',$tokenNo);
                $stmt->execute();
                $tokenRecords[$tokenNo]=$stmt->get_result()->fetch_assoc()?:['token_no'=>$tokenNo];
            } else {
                $tokenRecords[$tokenNo]=['token_no'=>$tokenNo];
            }
        }
        $token=$tokenRecords[$tokenNo];
    }
    $details=implode("\n",[
        'RPTA PASSENGER TOKEN',
        'Token: '.($token['token_no']??''),
        'Passenger: '.($token['full_name']??''),
        'Address: '.($token['address']??''),
        'District: '.($token['district']??''),
        'Service: '.($token['service_type']??''),
        'Date: '.($token['preferred_date']??''),
        'Time: '.($token['preferred_time']??'')
    ]);
    $tokenNo=(string)($token['token_no']??'');
    if(!array_key_exists($tokenNo,$qrCache)){
        $dataUri=(new \chillerlan\QRCode\QRCode())->render($details);
        $prefix='data:image/svg+xml;base64,';
        if(str_starts_with($dataUri,$prefix)){
            $svg=base64_decode(substr($dataUri,strlen($prefix)),true);
            if($svg!==false){
                $svg=preg_replace('/<svg(?=\s)/','<svg width="512" height="512"',$svg,1);
                $dataUri=$prefix.base64_encode($svg);
            }
        }
        $qrCache[$tokenNo]=$dataUri;
    }
    return $qrCache[$tokenNo];
}