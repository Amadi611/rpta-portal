<?php
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/includes/token_qr.php';

function token_pdf_text($value): string{
    $value=trim((string)$value);
    return $value===''?'-':$value;
}

function token_pdf_date($value, string $format): string{
    $value=trim((string)$value);
    if($value==='') return '-';
    $timestamp=strtotime($value);
    return $timestamp===false?$value:date($format,$timestamp);
}

function token_pdf_field(TCPDF $pdf, float $x, float $y, float $width, string $label, $value): float{
    $value=trim((string)$value);
    if($value==='') return $y;

    $pdf->SetFont('dejavusans','B',7);
    $pdf->SetTextColor(111,47,66);
    $pdf->SetXY($x,$y);
    $pdf->Cell($width,4,$label,0,1,'L');

    $pdf->SetFont('freeserif','',9);
    $pdf->SetTextColor(42,43,48);
    $height=max(5.2,$pdf->getStringHeight($width,$value,true,true));
    $pdf->MultiCell($width,$height,$value,0,'L',false,1,$x,$y+4);
    return $y+4+$height;
}

function make_token_pdf($file, $t): void{
    $logoPath=__DIR__.'/assets/images/sprpta_official_logo_pdf.png';
    if(!is_file($logoPath)){
        throw new RuntimeException('The local SPRPTA logo asset is missing.');
    }

    $pdf=new TCPDF('P','mm','A4',true,'UTF-8',false);
    $pdf->SetCreator('SPRPTA Passenger Portal');
    $pdf->SetAuthor(APP_NAME_EN);
    $pdf->SetTitle('SPRPTA Passenger Service Token - '.($t['token_no']??''));
    $pdf->SetSubject('Passenger service token');
    $pdf->SetPrintHeader(false);
    $pdf->SetPrintFooter(false);
    $pdf->SetMargins(14,14,14);
    $pdf->SetAutoPageBreak(false,0);
    $pdf->AddPage();

    $pdf->SetFillColor(91,16,43);
    $pdf->Rect(0,0,210,42,'F');
    $pdf->Image($logoPath,14,10,39,10);
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFont('dejavusans','B',11);
    $pdf->MultiCell(137,12,APP_NAME_EN,0,'L',false,1,59,9);
    $pdf->SetFont('dejavusans','',8);
    $pdf->SetTextColor(255,221,155);
    $pdf->SetXY(59,25);
    $pdf->Cell(137,5,'SOUTHERN PROVINCE  |  SRI LANKA',0,1,'L');
    $pdf->SetFont('dejavusans','B',10);
    $pdf->SetTextColor(255,255,255);
    $pdf->SetXY(59,31);
    $pdf->Cell(137,6,'PASSENGER SERVICE TOKEN',0,1,'L');

    $pdf->SetFillColor(255,255,255);
    $pdf->SetDrawColor(231,219,223);
    $pdf->RoundedRect(14,47,182,23,3,'1111','DF');
    $pdf->SetFont('dejavusans','B',7);
    $pdf->SetTextColor(111,47,66);
    $pdf->SetXY(20,51);
    $pdf->Cell(112,4,'TOKEN REFERENCE',0,1,'L');
    $pdf->SetFont('dejavusans','B',12);
    $pdf->SetTextColor(54,22,35);
    $pdf->SetXY(20,56);
    $pdf->Cell(118,8,token_pdf_text($t['token_no']??''),0,1,'L');
    $pdf->SetFillColor(255,239,222);
    $pdf->RoundedRect(151,53,39,11,2,'1111','F');
    $pdf->SetFont('dejavusans','B',8);
    $pdf->SetTextColor(132,67,13);
    $pdf->SetXY(153,56);
    $pdf->Cell(35,5,strtoupper(token_pdf_text($t['status']??'pending')),0,1,'C');

    $pdf->SetFillColor(255,255,255);
    $pdf->SetDrawColor(231,219,223);
    $pdf->RoundedRect(14,77,116,64,3,'1111','DF');
    $pdf->RoundedRect(135,77,61,64,3,'1111','DF');

    $pdf->SetFont('dejavusans','B',9);
    $pdf->SetTextColor(91,16,43);
    $pdf->SetXY(20,82);
    $pdf->Cell(105,5,'PASSENGER DETAILS',0,1,'L');
    $passengerFields=[
        ['Name',$t['full_name']??''],
        ['NIC / ID',$t['nic']??''],
        ['Phone',$t['phone']??''],
        ['Email',$t['email']??'']
    ];
    $fieldY=90;
    foreach($passengerFields as [$label,$value]){
        if(trim((string)$value)==='') continue;
        $fieldY=token_pdf_field($pdf,20,$fieldY,104,$label,$value)+1;
    }

    $pdf->SetFont('dejavusans','B',8);
    $pdf->SetTextColor(91,16,43);
    $pdf->SetXY(138,82);
    $pdf->Cell(55,5,'SCAN TOKEN DETAILS',0,1,'C');
    $pdf->write2DBarcode(token_qr_payload($t),'QRCODE,H',146,89,39,39,[
        'border'=>0,
        'vpadding'=>'auto',
        'hpadding'=>'auto',
        'fgcolor'=>[54,22,35],
        'bgcolor'=>[255,255,255]
    ],'N');
    $pdf->SetFont('dejavusans','',6.5);
    $pdf->SetTextColor(88,82,85);
    $pdf->MultiCell(53,8,'Present this QR code when visiting.',0,'C',false,1,139,130);

    $pdf->SetFillColor(255,255,255);
    $pdf->SetDrawColor(231,219,223);
    $pdf->RoundedRect(14,147,182,124,3,'1111','DF');
    $pdf->SetFont('dejavusans','B',9);
    $pdf->SetTextColor(91,16,43);
    $pdf->SetXY(20,152);
    $pdf->Cell(170,5,'SERVICE REQUEST',0,1,'L');

    $department=trim((string)($t['department']??''));
    $serviceFields=[
        ['Service',$t['service_type']??''],
        ['Section',$department===''?'-':department_label($department)],
        ['Preferred date',token_pdf_date($t['preferred_date']??'','d M Y')],
        ['Preferred time',token_pdf_date($t['preferred_time']??'','g:i A')],
        ['Vehicle type',$t['vehicle_type']??''],
        ['Vehicle registration',$t['vehicle_no']??''],
        ['Bus number',$t['bus_number']??''],
        ['Bus stand / halt',$t['bus_stand']??''],
        ['Route',$t['route']??'']
    ];
    $serviceFields=array_values(array_filter($serviceFields,static fn($field)=>trim((string)$field[1])!==''));
    $rowY=161;
    foreach(array_chunk($serviceFields,2) as $row){
        $leftBottom=token_pdf_field($pdf,20,$rowY,81,$row[0][0],$row[0][1]);
        $rightBottom=isset($row[1])?token_pdf_field($pdf,107,$rowY,82,$row[1][0],$row[1][1]):$rowY;
        $rowY=max($leftBottom,$rightBottom)+2;
    }
    foreach([
        ['Purpose',$t['purpose']??''],
        ['Additional details',$t['additional_details']??'']
    ] as [$label,$value]){
        if(trim((string)$value)==='') continue;
        $rowY=token_pdf_field($pdf,20,$rowY,169,$label,$value)+2;
    }

    $pdf->SetDrawColor(231,219,223);
    $pdf->Line(14,278,196,278);
    $pdf->SetFont('dejavusans','',7);
    $pdf->SetTextColor(82,76,79);
    $pdf->SetXY(14,282);
    $pdf->Cell(182,4,'Keep this token for your records.  |  General contact: '.CONTACT_PHONE.'  |  www.sprpta.lk',0,1,'C');

    $pdf->Output($file,'F');
}
?>
